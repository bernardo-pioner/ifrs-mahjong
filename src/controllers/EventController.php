<?php

declare(strict_types=1);

final class EventController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();

        $stmt = db()->query(
            "SELECT e.*, u.nome AS organizador
             FROM eventos e
             LEFT JOIN usuarios u ON u.id = e.admin_id
             ORDER BY e.data_inicio IS NULL, e.data_inicio ASC, e.id DESC"
        );

        $this->view('events/index', [
            'title' => 'Eventos',
            'user' => $user,
            'currentPath' => '/eventos',
            'eventos' => $stmt->fetchAll(),
        ]);
    }

    public function create(): void
    {
        $user = $this->requireAdmin();

        $this->view('events/create', [
            'title' => 'Criar evento',
            'user' => $user,
            'currentPath' => '/eventos',
            'old' => [],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        $user = $this->requireAdmin();
        verifyCsrf();

        $old = [
            'titulo' => request('titulo'),
            'tipo' => request('tipo', 'encontro'),
            'data' => request('data'),
            'horario' => request('horario'),
            'local' => request('local'),
            'limite' => request('limite'),
            'descricao' => request('descricao'),
            'inscricoes' => isset($_POST['inscricoes']) ? '1' : '0',
        ];

        $errors = [];

        if ($old['titulo'] === '') {
            $errors[] = 'Informe o nome do evento.';
        }

        if (!in_array($old['tipo'], ['encontro', 'oficina', 'torneio'], true)) {
            $errors[] = 'Selecione um tipo de evento válido.';
        }

        $dataHora = false;
        if ($old['data'] !== '' && $old['horario'] !== '') {
            $dataHora = DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i',
                $old['data'] . ' ' . $old['horario']
            );

            $dateErrors = DateTimeImmutable::getLastErrors();
            if (
                !$dataHora ||
                ($dateErrors !== false &&
                    ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
                $dataHora->format('Y-m-d H:i') !== $old['data'] . ' ' . $old['horario']
            ) {
                $dataHora = false;
            }
        }

        if ($dataHora === false) {
            $errors[] = 'Informe uma data e um horário válidos.';
        }

        $limite = null;
        if ($old['limite'] !== '') {
            $limite = filter_var($old['limite'], FILTER_VALIDATE_INT);

            if ($limite === false || $limite < 1 || $limite > 10000) {
                $errors[] = 'O limite deve ser um número entre 1 e 10.000.';
                $limite = null;
            }
        }

        if (mb_strlen($old['titulo']) > 160) {
            $errors[] = 'O nome deve ter no máximo 160 caracteres.';
        }

        if (mb_strlen($old['local']) > 200) {
            $errors[] = 'O local deve ter no máximo 200 caracteres.';
        }

        if (mb_strlen($old['descricao']) > 5000) {
            $errors[] = 'A descrição deve ter no máximo 5.000 caracteres.';
        }

        if ($errors !== []) {
            http_response_code(422);

            $this->view('events/create', [
                'title' => 'Criar evento',
                'user' => $user,
                'currentPath' => '/eventos',
                'old' => $old,
                'errors' => $errors,
            ]);
            return;
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $inicio = $dataHora->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare(
                'INSERT INTO eventos
                    (admin_id, tipo, titulo, descricao, data_inicio, local)
                 VALUES
                    (:admin_id, :tipo, :titulo, :descricao, :data_inicio, :local)'
            );

            $stmt->execute([
                ':admin_id' => (int) $user['id'],
                ':tipo' => $old['tipo'],
                ':titulo' => $old['titulo'],
                ':descricao' => $old['descricao'] !== '' ? $old['descricao'] : null,
                ':data_inicio' => $inicio,
                ':local' => $old['local'] !== '' ? $old['local'] : null,
            ]);

            $eventoId = (int) $pdo->lastInsertId();

            if ($old['tipo'] === 'torneio') {
                $stmt = $pdo->prepare(
                    'INSERT INTO torneios (evento_id, maximo_participantes)
                     VALUES (:evento_id, :limite)'
                );
                $stmt->execute([
                    ':evento_id' => $eventoId,
                    ':limite' => $limite,
                ]);
            } elseif ($old['tipo'] === 'oficina') {
                $stmt = $pdo->prepare(
                    'INSERT INTO oficinas (evento_id, vagas)
                     VALUES (:evento_id, :limite)'
                );
                $stmt->execute([
                    ':evento_id' => $eventoId,
                    ':limite' => $limite,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO encontros (evento_id, aberto_publico, observacoes)
                     VALUES (:evento_id, :aberto, :observacoes)'
                );
                $stmt->execute([
                    ':evento_id' => $eventoId,
                    ':aberto' => 1,
                    ':observacoes' => $old['inscricoes'] === '1'
                        ? 'Inscrições habilitadas'
                        : 'Inscrições não habilitadas',
                ]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        flash('success', 'Evento criado com sucesso.');
        redirect('/eventos');
    }
}
