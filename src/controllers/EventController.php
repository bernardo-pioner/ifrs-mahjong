<?php

declare(strict_types=1);

final class EventController extends Controller
{

    public function index(): void
    {
        $user = $this->requireAuth();

        // Valores recebidos pelos filtros.
        $busca = trim((string) ($_GET['busca'] ?? ''));
        $tipo = (string) ($_GET['tipo'] ?? '');
        $situacao = (string) ($_GET['situacao'] ?? '');

        // Validação das opções.
        $tiposValidos = ['encontro', 'oficina', 'torneio'];

        $situacoesValidas = [
            'nao_iniciado',
            'em_andamento',
            'encerrado'
        ];

        if (!in_array($tipo, $tiposValidos, true)) {
            $tipo = '';
        }

        if (!in_array($situacao, $situacoesValidas, true)) {
            $situacao = '';
        }

        // Consulta principal.
        $sql = "
            SELECT e.*, u.nome AS organizador
            FROM eventos e
            LEFT JOIN usuarios u ON u.id = e.admin_id
            WHERE 1 = 1
        ";

        $params = [];

        // Filtro por nome.
        if ($busca !== '') {
            $sql .= " AND e.titulo LIKE :busca";
            $params[':busca'] = '%' . $busca . '%';
        }

        // Filtro por tipo.
        if ($tipo !== '') {
            $sql .= " AND e.tipo = :tipo";
            $params[':tipo'] = $tipo;
        }

        // Filtro por situação.
        if ($situacao === 'nao_iniciado') {
            $sql .= "
                AND e.data_inicio IS NOT NULL
                AND datetime(e.data_inicio) > datetime('now', 'localtime')
            ";

        } elseif ($situacao === 'em_andamento') {
            $sql .= "
                AND e.data_inicio IS NOT NULL
                AND datetime(e.data_inicio) <= datetime('now', 'localtime')
                AND (
                    e.data_fim IS NULL
                    OR datetime(e.data_fim) >= datetime('now', 'localtime')
                )
            ";

        } elseif ($situacao === 'encerrado') {
            $sql .= "
                AND e.data_fim IS NOT NULL
                AND datetime(e.data_fim) < datetime('now', 'localtime')
            ";
        }

        // Mantém a ordenação original.
        $sql .= "
            ORDER BY
                e.data_inicio IS NULL,
                e.data_inicio ASC,
                e.id DESC
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        $this->view('events/index', [
            'title' => 'Eventos',
            'user' => $user,
            'currentPath' => '/eventos',
            'eventos' => $stmt->fetchAll(),
            'busca' => $busca,
            'tipoFiltro' => $tipo,
            'situacaoFiltro' => $situacao,
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
            'data_fim' => request('data_fim'),
            'horario_fim' => request('horario_fim'),
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
        $dataHoraFim = false;

        $inicioTexto = $old['data'] . ' ' . $old['horario'];
        $fimTexto = $old['data_fim'] . ' ' . $old['horario_fim'];

        if ($old['data'] !== '' && $old['horario'] !== '') {
            $dataHora = DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i',
                $inicioTexto
            );

            $dateErrors = DateTimeImmutable::getLastErrors();

            if (
                !$dataHora ||
                ($dateErrors !== false &&
                    ($dateErrors['warning_count'] > 0 ||
                    $dateErrors['error_count'] > 0)) ||
                $dataHora->format('Y-m-d H:i') !== $inicioTexto
            ) {
                $dataHora = false;
            }
        }

        if (
            $old['data_fim'] !== '' &&
            $old['horario_fim'] !== ''
        ) {
            $dataHoraFim = DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i',
                $fimTexto
            );

            $dateErrorsFim = DateTimeImmutable::getLastErrors();

            if (
                !$dataHoraFim ||
                ($dateErrorsFim !== false &&
                    ($dateErrorsFim['warning_count'] > 0 ||
                    $dateErrorsFim['error_count'] > 0)) ||
                $dataHoraFim->format('Y-m-d H:i') !== $fimTexto
            ) {
                $dataHoraFim = false;
            }
        }

        if ($dataHora === false) {
            $errors[] = 'Informe uma data e um horário de início válidos.';
        }

        if ($dataHoraFim === false) {
            $errors[] = 'Informe uma data e um horário de término válidos.';
        }

        if (
            $dataHora !== false &&
            $dataHoraFim !== false &&
            $dataHoraFim <= $dataHora
        ) {
            $errors[] = 'O término deve ser posterior ao início do evento.';
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
            $fim = $dataHoraFim->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare(
                'INSERT INTO eventos
                    (admin_id, tipo, titulo, descricao, data_inicio, data_fim, local)
                VALUES
                    (:admin_id, :tipo, :titulo, :descricao, :data_inicio, :data_fim, :local)'
            );

            $stmt->execute([
                ':admin_id' => (int) $user['id'],
                ':tipo' => $old['tipo'],
                ':titulo' => $old['titulo'],
                ':descricao' => $old['descricao'] !== ''
                    ? $old['descricao']
                    : null,
                ':data_inicio' => $inicio,
                ':data_fim' => $fim,
                ':local' => $old['local'] !== ''
                    ? $old['local']
                    : null,
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

    public function edit(string $id): void
    {
        $user = $this->requireAdmin();

        $stmt = db()->prepare(
            'SELECT * FROM eventos WHERE id = :id'
        );

        $stmt->execute([
            ':id' => (int) $id,
        ]);

        $evento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$evento) {
            http_response_code(404);
            echo 'Evento não encontrado.';
            return;
        }

        $inicio = !empty($evento['data_inicio'])
            ? new DateTimeImmutable($evento['data_inicio'])
            : null;

        $fim = !empty($evento['data_fim'])
            ? new DateTimeImmutable($evento['data_fim'])
            : null;
        $limite = '';
        $inscricoes = '1';

        if ($evento['tipo'] === 'torneio') {
            $stmt = db()->prepare(
                'SELECT maximo_participantes
                FROM torneios WHERE evento_id = :id'
            );
            $stmt->execute([':id' => (int) $id]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);

            $limite = isset($dados['maximo_participantes'])
                ? (string) $dados['maximo_participantes']
                : '';

        } elseif ($evento['tipo'] === 'oficina') {
            $stmt = db()->prepare(
                'SELECT vagas
                FROM oficinas WHERE evento_id = :id'
            );
            $stmt->execute([':id' => (int) $id]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);

            $limite = isset($dados['vagas'])
                ? (string) $dados['vagas']
                : '';

        } elseif ($evento['tipo'] === 'encontro') {
            $stmt = db()->prepare(
                'SELECT observacoes
                FROM encontros WHERE evento_id = :id'
            );
            $stmt->execute([':id' => (int) $id]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);

            $inscricoes = ($dados['observacoes'] ?? '') === 'Inscrições não habilitadas'
                ? '0'
                : '1';
        }

        $old = [
            'titulo' => $evento['titulo'],
            'tipo' => $evento['tipo'],
            'data' => $inicio?->format('Y-m-d') ?? '',
            'horario' => $inicio?->format('H:i') ?? '14:00',
            'data_fim' => $fim?->format('Y-m-d') ?? '',
            'horario_fim' => $fim?->format('H:i') ?? '16:00',
            'local' => $evento['local'] ?? '',
            'descricao' => $evento['descricao'] ?? '',
            'limite' => $limite,
            'inscricoes' => $inscricoes,
        ];

        $this->view('events/edit', [
            'title' => 'Editar evento',
            'user' => $user,
            'currentPath' => '/eventos',
            'evento' => $evento,
            'old' => $old,
            'errors' => [],
        ]);
    }
    public function update(string $id): void
    {
        $user = $this->requireAdmin();
        verifyCsrf();

        $pdo = db();

        $stmt = $pdo->prepare('SELECT * FROM eventos WHERE id = :id');
        $stmt->execute([':id' => (int) $id]);
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$evento) {
            http_response_code(404);
            echo 'Evento não encontrado.';
            return;
        }

        $old = [
            'titulo' => request('titulo'),
            'tipo' => request('tipo', 'encontro'),
            'data' => request('data'),
            'horario' => request('horario'),
            'data_fim' => request('data_fim'),
            'horario_fim' => request('horario_fim'),
            'local' => request('local'),
            'limite' => request('limite'),
            'descricao' => request('descricao'),
            'inscricoes' => isset($_POST['inscricoes']) ? '1' : '0',
        ];

        $errors = [];

        if ($old['titulo'] === '' || mb_strlen($old['titulo']) > 160) {
            $errors[] = 'Informe um nome de evento com até 160 caracteres.';
        }

        if (!in_array($old['tipo'], ['encontro', 'oficina', 'torneio'], true)) {
            $errors[] = 'Selecione um tipo de evento válido.';
        }

        $inicio = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            $old['data'] . ' ' . $old['horario']
        );

        $fim = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            $old['data_fim'] . ' ' . $old['horario_fim']
        );

        if (
            !$inicio ||
            $inicio->format('Y-m-d H:i') !== $old['data'] . ' ' . $old['horario']
        ) {
            $errors[] = 'Informe uma data e um horário de início válidos.';
        }

        if (
            !$fim ||
            $fim->format('Y-m-d H:i') !== $old['data_fim'] . ' ' . $old['horario_fim']
        ) {
            $errors[] = 'Informe uma data e um horário de término válidos.';
        }

        if ($inicio && $fim && $fim <= $inicio) {
            $errors[] = 'O término deve ser posterior ao início do evento.';
        }

        if (mb_strlen($old['local']) > 200) {
            $errors[] = 'O local deve ter no máximo 200 caracteres.';
        }

        if (mb_strlen($old['descricao']) > 5000) {
            $errors[] = 'A descrição deve ter no máximo 5.000 caracteres.';
        }

        $limite = null;

        if ($old['limite'] !== '') {
            $limite = filter_var($old['limite'], FILTER_VALIDATE_INT);

            if ($limite === false || $limite < 1 || $limite > 10000) {
                $errors[] = 'O limite deve ser um número entre 1 e 10.000.';
            }
        }

        if ($errors !== []) {
            http_response_code(422);

            $this->view('events/edit', [
                'title' => 'Editar evento',
                'user' => $user,
                'currentPath' => '/eventos',
                'evento' => $evento,
                'old' => $old,
                'errors' => $errors,
            ]);

            return;
        }

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'UPDATE eventos
                SET titulo = :titulo,
                    tipo = :tipo,
                    descricao = :descricao,
                    data_inicio = :data_inicio,
                    data_fim = :data_fim,
                    local = :local
                WHERE id = :id'
            );

            $stmt->execute([
                ':titulo' => $old['titulo'],
                ':tipo' => $old['tipo'],
                ':descricao' => $old['descricao'] !== '' ? $old['descricao'] : null,
                ':data_inicio' => $inicio->format('Y-m-d H:i:s'),
                ':data_fim' => $fim->format('Y-m-d H:i:s'),
                ':local' => $old['local'] !== '' ? $old['local'] : null,
                ':id' => (int) $id,
            ]);

            // Atualiza o registro específico do tipo de evento.
            $tabelas = [
                'torneio' => 'torneios',
                'oficina' => 'oficinas',
                'encontro' => 'encontros',
            ];

            $tipoAnterior = $evento['tipo'];

            if ($tipoAnterior !== $old['tipo']) {
                $stmt = $pdo->prepare(
                    'DELETE FROM ' . $tabelas[$tipoAnterior] . '
                    WHERE evento_id = :evento_id'
                );
                $stmt->execute([':evento_id' => (int) $id]);

                if ($old['tipo'] === 'torneio') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO torneios (evento_id, maximo_participantes)
                        VALUES (:id, :limite)'
                    );
                    $stmt->execute([':id' => (int) $id, ':limite' => $limite]);
                } elseif ($old['tipo'] === 'oficina') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO oficinas (evento_id, vagas)
                        VALUES (:id, :limite)'
                    );
                    $stmt->execute([':id' => (int) $id, ':limite' => $limite]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO encontros (evento_id, aberto_publico, observacoes)
                        VALUES (:id, 1, :observacoes)'
                    );
                    $stmt->execute([
                        ':id' => (int) $id,
                        ':observacoes' => $old['inscricoes'] === '1'
                            ? 'Inscrições habilitadas'
                            : 'Inscrições não habilitadas',
                    ]);
                }
            } elseif ($old['tipo'] === 'torneio') {
                $stmt = $pdo->prepare(
                    'UPDATE torneios
                    SET maximo_participantes = :limite
                    WHERE evento_id = :id'
                );
                $stmt->execute([':limite' => $limite, ':id' => (int) $id]);
            } elseif ($old['tipo'] === 'oficina') {
                $stmt = $pdo->prepare(
                    'UPDATE oficinas
                    SET vagas = :limite
                    WHERE evento_id = :id'
                );
                $stmt->execute([':limite' => $limite, ':id' => (int) $id]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE encontros
                    SET observacoes = :observacoes
                    WHERE evento_id = :id'
                );
                $stmt->execute([
                    ':observacoes' => $old['inscricoes'] === '1'
                        ? 'Inscrições habilitadas'
                        : 'Inscrições não habilitadas',
                    ':id' => (int) $id,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        flash('success', 'Evento atualizado com sucesso.');
        redirect('/eventos');
    }
    public function show(string $id): void
    {
        $user = $this->requireAuth();

        $stmt = db()->prepare(
            'SELECT e.*, u.nome AS organizador
            FROM eventos e
            LEFT JOIN usuarios u ON u.id = e.admin_id
            WHERE e.id = :id'
        );

        $stmt->execute([
            ':id' => (int) $id,
        ]);

        $evento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$evento) {
            http_response_code(404);
            echo 'Evento não encontrado.';
            return;
        }

        $stmt = db()->prepare(
            'SELECT
                u.nome,
                p.apelido,
                ep.status,
                ep.inscrito_em
            FROM evento_participantes ep
            INNER JOIN participantes p
                ON p.usuario_id = ep.participante_id
            INNER JOIN usuarios u
                ON u.id = p.usuario_id
            WHERE ep.evento_id = :evento_id
            ORDER BY ep.inscrito_em ASC'
        );

        $stmt->execute([
            ':evento_id' => (int) $id,
        ]);

        $participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->view('events/show', [
            'title' => $evento['titulo'],
            'user' => $user,
            'currentPath' => '/eventos',
            'evento' => $evento,
            'participantes' => $participantes,
        ]);
    }


    public function destroy(string $id): void
    {
        $this->requireAdmin();
        verifyCsrf();

        $pdo = db();
        $eventoId = (int) $id;

        $stmt = $pdo->prepare(
            'SELECT id, tipo FROM eventos WHERE id = :id'
        );
        $stmt->execute([':id' => $eventoId]);

        $evento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$evento) {
            flash('error', 'Evento não encontrado.');
            redirect('/eventos');
            return;
        }

        // Impede excluir eventos com partidas ou rankings vinculados.
        $stmt = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*)
                FROM partidas_competitivas
                WHERE torneio_id = :torneio_partidas)
                +
                (SELECT COUNT(*)
                FROM partidas_casuais
                WHERE encontro_id = :encontro_partidas)
                +
                (SELECT COUNT(*)
                FROM rankings_competitivos
                WHERE torneio_id = :torneio_rankings)
                +
                (SELECT COUNT(*)
                FROM rankings_casuais
                WHERE encontro_id = :encontro_rankings)'
        );

        $stmt->execute([
            ':torneio_partidas' => $eventoId,
            ':encontro_partidas' => $eventoId,
            ':torneio_rankings' => $eventoId,
            ':encontro_rankings' => $eventoId,
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            flash(
                'error',
                'Este evento possui partidas ou rankings vinculados e não pode ser excluído.'
            );
            redirect('/eventos/' . $eventoId);
            return;
        }

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'DELETE FROM eventos WHERE id = :id'
            );

            $stmt->execute([':id' => $eventoId]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        flash('success', 'Evento excluído com sucesso.');
        redirect('/eventos');
    }
}
