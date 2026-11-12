<?php

declare(strict_types=1);

final class EventController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();

        $busca = trim((string) ($_GET['busca'] ?? ''));
        $tipo = (string) ($_GET['tipo'] ?? '');
        $inscricao = (string) ($_GET['inscricao'] ?? '');
        $situacao = (string) ($_GET['situacao'] ?? '');
        $ehAdmin = ($user['role'] ?? '') === 'admin';

        $tiposValidos = ['encontro', 'oficina', 'torneio'];
        $inscricoesValidas = ['pendente', 'inscrito', 'nao_inscrito'];
        $situacoesValidas = ['em_andamento', 'encerrado'];

        if (!in_array($tipo, $tiposValidos, true)) {
            $tipo = '';
        }

        if ($ehAdmin || !in_array($inscricao, $inscricoesValidas, true)) {
            $inscricao = '';
        }

        if (!$ehAdmin || !in_array($situacao, $situacoesValidas, true)) {
            $situacao = '';
        }

        $userId = (int) $user['id'];

        $sql = '
            SELECT
                e.*,
                u.nome AS organizador,
                ep.status AS minha_inscricao
            FROM eventos e
            LEFT JOIN usuarios u ON u.id = e.admin_id
            LEFT JOIN evento_participantes ep
                ON ep.evento_id = e.id
               AND ep.participante_id = :user_id
               AND ep.status <> \'cancelado\'
            WHERE 1 = 1
        ';

        $params = [':user_id' => $userId];

        if ($busca !== '') {
            $sql .= ' AND e.titulo LIKE :busca';
            $params[':busca'] = '%' . $busca . '%';
        }

        if ($tipo !== '') {
            $sql .= ' AND e.tipo = :tipo';
            $params[':tipo'] = $tipo;
        }

        if ($inscricao === 'pendente') {
            $sql .= " AND ep.status = 'pendente'";
        } elseif ($inscricao === 'inscrito') {
            $sql .= " AND ep.status = 'aprovado'";
        } elseif ($inscricao === 'nao_inscrito') {
            $sql .= ' AND ep.evento_id IS NULL';
        }

        $dataReferencia = "COALESCE(NULLIF(e.data_fim, ''), NULLIF(e.data_inicio, ''))";
        if ($situacao === 'encerrado') {
            $sql .= " AND {$dataReferencia} IS NOT NULL AND date({$dataReferencia}) <= date('now', 'localtime', '-1 day')";
        } elseif ($situacao === 'em_andamento') {
            $sql .= " AND ({$dataReferencia} IS NULL OR date({$dataReferencia}) > date('now', 'localtime', '-1 day'))";
        }

        $sql .= '
            ORDER BY
                e.data_inicio IS NULL,
                e.data_inicio ASC,
                e.id DESC
        ';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        $this->view('events/index', [
            'title' => 'Eventos',
            'user' => $user,
            'currentPath' => '/eventos',
            'eventos' => $stmt->fetchAll(),
            'busca' => $busca,
            'tipoFiltro' => $tipo,
            'inscricaoFiltro' => $inscricao,
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

        $old = $this->collectFormInput();
        [$errors, $parsed] = $this->validateEventForm($old, true);

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

        $base = [
            'admin_id' => (int) $user['id'],
            'tipo' => $parsed['tipo'],
            'modalidade' => $parsed['modalidade'],
            'titulo' => $parsed['titulo'],
            'descricao' => $parsed['descricao'],
            'data_inicio' => $parsed['inicio']->format('Y-m-d H:i:s'),
            'data_fim' => $parsed['fim']?->format('Y-m-d H:i:s'),
            'local' => $parsed['local'],
            'inscricoes_abertas' => $parsed['inscricoes_abertas'],
            'limite' => $parsed['limite'],
            'taxa' => $parsed['taxa'],
        ];

        if ($parsed['recorrente']) {
            Event::createWithRecurrence(
                $base,
                $parsed['recorrencia_dias'],
                $parsed['recorrencia_ocorrencias']
            );
            flash(
                'success',
                'Série de ' . $parsed['recorrencia_ocorrencias'] . ' eventos criada com sucesso.'
            );
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                Event::createOne($base);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            flash('success', 'Evento criado com sucesso.');
        }

        redirect('/eventos');
    }

    public function edit(string $id): void
    {
        $user = $this->requireAdmin();
        $evento = Event::findById((int) $id);

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

        $details = Event::typeDetails($evento);

        $old = [
            'titulo' => $evento['titulo'],
            'tipo' => $evento['tipo'],
            'modalidade' => (string) ($evento['modalidade'] ?? 'livre'),
            'data' => $inicio?->format('Y-m-d') ?? '',
            'horario' => $inicio?->format('H:i') ?? '14:00',
            'tem_termino' => $fim !== null ? '1' : '0',
            'data_fim' => $fim?->format('Y-m-d') ?? '',
            'horario_fim' => $fim?->format('H:i') ?? '16:00',
            'local' => $evento['local'] ?? '',
            'descricao' => $evento['descricao'] ?? '',
            'tem_limite' => $details['limite'] !== null ? '1' : '0',
            'limite' => $details['limite'] !== null ? (string) $details['limite'] : '',
            'tem_pagamento' => $details['taxa'] > 0 ? '1' : '0',
            'taxa' => $details['taxa'] > 0
                ? number_format($details['taxa'], 2, '.', '')
                : '',
            'inscricoes' => (int) ($evento['inscricoes_abertas'] ?? 1) === 1 ? '1' : '0',
            'recorrente' => '0',
            'recorrencia_dias' => '7',
            'recorrencia_ocorrencias' => '4',
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
        $evento = Event::findById((int) $id);

        if (!$evento) {
            http_response_code(404);
            echo 'Evento não encontrado.';
            return;
        }

        $old = $this->collectFormInput();
        [$errors, $parsed] = $this->validateEventForm($old, false);

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
                     modalidade = :modalidade,
                     descricao = :descricao,
                     data_inicio = :data_inicio,
                     data_fim = :data_fim,
                     local = :local,
                     inscricoes_abertas = :inscricoes_abertas,
                     atualizado_em = datetime(\'now\')
                 WHERE id = :id'
            );
            $stmt->execute([
                ':titulo' => $parsed['titulo'],
                ':tipo' => $parsed['tipo'],
                ':modalidade' => $parsed['modalidade'],
                ':descricao' => $parsed['descricao'],
                ':data_inicio' => $parsed['inicio']->format('Y-m-d H:i:s'),
                ':data_fim' => $parsed['fim']?->format('Y-m-d H:i:s'),
                ':local' => $parsed['local'],
                ':inscricoes_abertas' => $parsed['inscricoes_abertas'],
                ':id' => (int) $id,
            ]);

            $tabelas = [
                'torneio' => 'torneios',
                'oficina' => 'oficinas',
                'encontro' => 'encontros',
            ];

            $tipoAnterior = (string) $evento['tipo'];

            if ($tipoAnterior !== $parsed['tipo']) {
                $stmt = $pdo->prepare(
                    'DELETE FROM ' . $tabelas[$tipoAnterior] . ' WHERE evento_id = :evento_id'
                );
                $stmt->execute([':evento_id' => (int) $id]);
                Event::insertTypeRow(
                    (int) $id,
                    $parsed['tipo'],
                    $parsed['limite'],
                    $parsed['taxa'],
                    $parsed['inscricoes_abertas']
                );
            } elseif ($parsed['tipo'] === 'torneio') {
                $stmt = $pdo->prepare(
                    'UPDATE torneios
                     SET maximo_participantes = :limite,
                         taxa_participacao = :taxa
                     WHERE evento_id = :id'
                );
                $stmt->execute([
                    ':limite' => $parsed['limite'],
                    ':taxa' => $parsed['taxa'],
                    ':id' => (int) $id,
                ]);
            } elseif ($parsed['tipo'] === 'oficina') {
                $stmt = $pdo->prepare(
                    'UPDATE oficinas
                     SET vagas = :limite,
                         taxa_inscricao = :taxa
                     WHERE evento_id = :id'
                );
                $stmt->execute([
                    ':limite' => $parsed['limite'],
                    ':taxa' => $parsed['taxa'],
                    ':id' => (int) $id,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE encontros
                     SET observacoes = :observacoes
                     WHERE evento_id = :id'
                );
                $stmt->execute([
                    ':observacoes' => $parsed['inscricoes_abertas'] === 1
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
        $evento = Event::findById((int) $id);

        if (!$evento) {
            http_response_code(404);
            echo 'Evento não encontrado.';
            return;
        }

        $details = Event::typeDetails($evento);
        $participantes = Event::listParticipants((int) $id, (int) $user['id']);
        $minhaParticipacao = Event::findParticipation((int) $id, (int) $user['id']);
        $meuPagamento = Event::findPayment((int) $id, (int) $user['id']);
        $vagasOcupadas = Event::countActiveParticipants((int) $id);
        $temVaga = Event::hasCapacity($evento);
        $ehAdmin = ($user['role'] ?? '') === 'admin';
        $userId = (int) $user['id'];

        $levelLabels = [
            'iniciante' => 'Iniciante',
            'intermediario' => 'Intermediário',
            'avancado' => 'Avançado',
        ];

        foreach ($participantes as &$participante) {
            $dono = (int) ($participante['usuario_id'] ?? 0) === $userId;
            $podeVerComprovante = $ehAdmin || $dono;
            $temComprovante = !empty($participante['comprovante']);
            $level = (string) ($participante['level'] ?? '');

            $participante['pode_ver_comprovante'] = $podeVerComprovante && $temComprovante;
            $participante['photo_url'] = userPhotoUrl($participante);
            $participante['member_since'] = formatMemberSince($participante['created_at'] ?? null);
            $participante['level_label'] = $levelLabels[$level] ?? '';
            unset($participante['comprovante'], $participante['photo'], $participante['created_at']);
        }
        unset($participante);

        $this->view('events/show', [
            'title' => $evento['titulo'],
            'user' => $user,
            'currentPath' => '/eventos',
            'evento' => $evento,
            'participantes' => $participantes,
            'details' => $details,
            'minhaParticipacao' => $minhaParticipacao,
            'meuPagamento' => $meuPagamento,
            'vagasOcupadas' => $vagasOcupadas,
            'temVaga' => $temVaga,
        ]);
    }

    public function downloadComprovante(string $id, string $participanteId): void
    {
        $user = $this->requireAuth();
        $eventoId = (int) $id;
        $targetId = (int) $participanteId;
        $ehAdmin = ($user['role'] ?? '') === 'admin';

        if (!$ehAdmin && (int) $user['id'] !== $targetId) {
            http_response_code(403);
            echo 'Acesso negado.';
            return;
        }

        $evento = Event::findById($eventoId);
        if (!$evento) {
            http_response_code(404);
            echo 'Evento não encontrado.';
            return;
        }

        $pagamento = Event::findPayment($eventoId, $targetId);
        if ($pagamento === null || empty($pagamento['comprovante'])) {
            http_response_code(404);
            echo 'Comprovante não encontrado.';
            return;
        }

        $absolute = Event::resolveComprovantePath((string) $pagamento['comprovante']);
        if ($absolute === null) {
            http_response_code(404);
            echo 'Arquivo do comprovante não encontrado.';
            return;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($absolute);
        $allowed = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];
        if (!isset($allowed[$mime])) {
            http_response_code(403);
            echo 'Arquivo inválido.';
            return;
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($absolute));
        header(
            'Content-Disposition: inline; filename="comprovante-' . $eventoId . '-' . $targetId . '.' . $allowed[$mime] . '"'
        );
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($absolute);
        exit;
    }

    public function register(string $id): void
    {
        $user = $this->requireAuth();
        verifyCsrf();

        $evento = Event::findById((int) $id);
        if (!$evento) {
            flash('error', 'Evento não encontrado.');
            redirect('/eventos');
            return;
        }

        $result = Event::register($evento, (int) $user['id']);
        if (!$result['ok']) {
            flash('error', (string) $result['error']);
            redirect('/eventos/' . (int) $id);
            return;
        }

        $details = Event::typeDetails($evento);
        $precisaPagamento = in_array($evento['tipo'], ['torneio', 'oficina'], true)
            && $details['taxa'] > 0;

        if ($precisaPagamento) {
            $mensagem = 'Inscrição pendente. Envie o comprovante abaixo.';
        } elseif (in_array($evento['tipo'], ['torneio', 'oficina'], true)) {
            $mensagem = 'Inscrição enviada. Aguarde a aprovação.';
        } else {
            $mensagem = 'Inscrição realizada com sucesso.';
        }

        flash('success', $mensagem);
        redirect('/eventos/' . (int) $id);
    }

    public function uploadComprovante(string $id): void
    {
        $user = $this->requireAuth();
        verifyCsrf();

        $evento = Event::findById((int) $id);
        if (!$evento) {
            flash('error', 'Evento não encontrado.');
            redirect('/eventos');
            return;
        }

        $upload = $this->storePaymentProof((int) $user['id'], (int) $id);
        if ($upload['error'] !== null || $upload['path'] === null) {
            flash('error', $upload['error'] ?? 'Não foi possível enviar o comprovante.');
            redirect('/eventos/' . (int) $id);
            return;
        }

        $result = Event::upsertPaymentProof($evento, (int) $user['id'], $upload['path']);
        if (!$result['ok']) {
            $absolute = Event::resolveComprovantePath($upload['path']);
            if ($absolute !== null) {
                unlink($absolute);
            }
            flash('error', (string) $result['error']);
            redirect('/eventos/' . (int) $id);
            return;
        }

        if (!empty($result['previous_path']) && $result['previous_path'] !== $upload['path']) {
            $previousAbsolute = Event::resolveComprovantePath((string) $result['previous_path']);
            if ($previousAbsolute !== null) {
                unlink($previousAbsolute);
            }
        }

        flash('success', 'Comprovante enviado. Aguarde a análise.');
        redirect('/eventos/' . (int) $id);
    }

    public function updateParticipantStatus(string $id, string $participanteId): void
    {
        $this->requireAdmin();
        verifyCsrf();

        $status = request('status');
        $result = Event::updateParticipantStatus((int) $id, (int) $participanteId, $status);

        if (!$result['ok']) {
            flash('error', (string) $result['error']);
        } else {
            flash('success', 'Status da participação atualizado.');
        }

        redirect('/eventos/' . (int) $id);
    }

    public function destroy(string $id): void
    {
        $this->requireAdmin();
        verifyCsrf();

        $pdo = db();
        $eventoId = (int) $id;
        $evento = Event::findById($eventoId);

        if (!$evento) {
            flash('error', 'Evento não encontrado.');
            redirect('/eventos');
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM partidas_competitivas WHERE torneio_id = :torneio_partidas)
                +
                (SELECT COUNT(*) FROM partidas_casuais WHERE encontro_id = :encontro_partidas)
                +
                (SELECT COUNT(*) FROM rankings_competitivos WHERE torneio_id = :torneio_rankings)
                +
                (SELECT COUNT(*) FROM rankings_casuais WHERE encontro_id = :encontro_rankings)'
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
            $stmt = $pdo->prepare('DELETE FROM eventos WHERE id = :id');
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

    /** @return array<string, string> */
    private function collectFormInput(): array
    {
        return [
            'titulo' => request('titulo'),
            'tipo' => request('tipo', 'encontro'),
            'modalidade' => request('modalidade', 'livre'),
            'data' => request('data'),
            'horario' => request('horario'),
            'tem_termino' => isset($_POST['tem_termino']) ? '1' : '0',
            'data_fim' => request('data_fim'),
            'horario_fim' => request('horario_fim'),
            'local' => request('local'),
            'tem_limite' => isset($_POST['tem_limite']) ? '1' : '0',
            'limite' => request('limite'),
            'tem_pagamento' => isset($_POST['tem_pagamento']) ? '1' : '0',
            'taxa' => request('taxa'),
            'descricao' => request('descricao'),
            'inscricoes' => isset($_POST['inscricoes']) ? '1' : '0',
            'recorrente' => isset($_POST['recorrente']) ? '1' : '0',
            'recorrencia_dias' => request('recorrencia_dias', '7'),
            'recorrencia_ocorrencias' => request('recorrencia_ocorrencias', '4'),
        ];
    }

    /**
     * @param array<string, string> $old
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    private function validateEventForm(array $old, bool $allowRecurrence): array
    {
        $errors = [];

        $titulo = trim($old['titulo']);
        $tipo = $old['tipo'];
        $local = trim($old['local']);
        $descricao = trim($old['descricao']);

        if ($titulo === '') {
            $errors[] = 'Informe o nome do evento.';
        } elseif (mb_strlen($titulo) > 160) {
            $errors[] = 'O nome deve ter no máximo 160 caracteres.';
        }

        if (!in_array($tipo, ['encontro', 'oficina', 'torneio'], true)) {
            $errors[] = 'Selecione um tipo de evento válido.';
        }

        $modalidade = $old['modalidade'] ?? 'livre';
        if ($tipo !== 'torneio') {
            $modalidade = 'livre';
        } elseif (!array_key_exists($modalidade, eventModalidadeOptions())) {
            $errors[] = 'Selecione uma modalidade válida.';
            $modalidade = 'livre';
        }

        $inicioTexto = $old['data'] . ' ' . $old['horario'];
        $inicio = false;
        if ($old['data'] !== '' && $old['horario'] !== '') {
            $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $inicioTexto);
            $dateErrors = DateTimeImmutable::getLastErrors();
            if (
                !$inicio ||
                ($dateErrors !== false &&
                    ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
                $inicio->format('Y-m-d H:i') !== $inicioTexto
            ) {
                $inicio = false;
            }
        }

        if ($inicio === false) {
            $errors[] = 'Informe uma data e um horário de início válidos.';
        }

        $fim = null;
        if ($old['tem_termino'] === '1') {
            $fimTexto = $old['data_fim'] . ' ' . $old['horario_fim'];
            $fimParsed = false;
            if ($old['data_fim'] !== '' && $old['horario_fim'] !== '') {
                $fimParsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $fimTexto);
                $dateErrorsFim = DateTimeImmutable::getLastErrors();
                if (
                    !$fimParsed ||
                    ($dateErrorsFim !== false &&
                        ($dateErrorsFim['warning_count'] > 0 || $dateErrorsFim['error_count'] > 0)) ||
                    $fimParsed->format('Y-m-d H:i') !== $fimTexto
                ) {
                    $fimParsed = false;
                }
            }

            if ($fimParsed === false) {
                $errors[] = 'Informe uma data e um horário de término válidos.';
            } else {
                $fim = $fimParsed;
                if ($inicio !== false && $fim <= $inicio) {
                    $errors[] = 'O término deve ser posterior ao início do evento.';
                }
            }
        }

        if (mb_strlen($local) > 200) {
            $errors[] = 'O local deve ter no máximo 200 caracteres.';
        }

        if (mb_strlen($descricao) > 5000) {
            $errors[] = 'A descrição deve ter no máximo 5.000 caracteres.';
        }

        $limite = null;
        if (in_array($tipo, ['torneio', 'oficina'], true) && ($old['tem_limite'] ?? '0') === '1') {
            $limite = filter_var($old['limite'], FILTER_VALIDATE_INT);
            if ($limite === false || $limite < 1 || $limite > 10000) {
                $errors[] = 'Informe um limite de participantes entre 1 e 10.000.';
                $limite = null;
            }
        }

        $taxa = 0.0;
        if (in_array($tipo, ['torneio', 'oficina'], true) && $old['tem_pagamento'] === '1') {
            $taxaRaw = str_replace(',', '.', trim($old['taxa']));
            if ($taxaRaw === '' || !is_numeric($taxaRaw) || (float) $taxaRaw <= 0) {
                $errors[] = 'Informe um valor de pagamento válido maior que zero.';
            } else {
                $taxa = round((float) $taxaRaw, 2);
            }
        }

        $recorrente = $allowRecurrence && $old['recorrente'] === '1';
        $recorrenciaDias = 7;
        $recorrenciaOcorrencias = 1;

        if ($recorrente) {
            $recorrenciaDias = filter_var($old['recorrencia_dias'], FILTER_VALIDATE_INT);
            $recorrenciaOcorrencias = filter_var($old['recorrencia_ocorrencias'], FILTER_VALIDATE_INT);

            if ($recorrenciaDias === false || $recorrenciaDias < 1 || $recorrenciaDias > 365) {
                $errors[] = 'O intervalo de recorrência deve ser entre 1 e 365 dias.';
                $recorrenciaDias = 7;
            }

            if (
                $recorrenciaOcorrencias === false ||
                $recorrenciaOcorrencias < 2 ||
                $recorrenciaOcorrencias > 52
            ) {
                $errors[] = 'Informe entre 2 e 52 ocorrências para a recorrência.';
                $recorrenciaOcorrencias = 4;
            }
        }

        $parsed = [
            'titulo' => $titulo,
            'tipo' => $tipo,
            'modalidade' => $modalidade,
            'inicio' => $inicio instanceof DateTimeImmutable ? $inicio : null,
            'fim' => $fim,
            'local' => $local !== '' ? $local : null,
            'descricao' => $descricao !== '' ? $descricao : null,
            'limite' => $limite,
            'taxa' => $taxa,
            'inscricoes_abertas' => $old['inscricoes'] === '1' ? 1 : 0,
            'recorrente' => $recorrente,
            'recorrencia_dias' => (int) $recorrenciaDias,
            'recorrencia_ocorrencias' => (int) $recorrenciaOcorrencias,
        ];

        return [$errors, $parsed];
    }

    /** @return array{path: ?string, error: ?string} */
    private function storePaymentProof(int $userId, int $eventoId): array
    {
        if (!isset($_FILES['comprovante']) || !is_array($_FILES['comprovante'])) {
            return ['path' => null, 'error' => 'Anexe o comprovante de pagamento.'];
        }

        $error = (int) ($_FILES['comprovante']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => 'Anexe o comprovante de pagamento.'];
        }

        if ($error !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => 'Não foi possível enviar o comprovante. Tente novamente.'];
        }

        $tmp = (string) ($_FILES['comprovante']['tmp_name'] ?? '');
        $size = (int) ($_FILES['comprovante']['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0) {
            return ['path' => null, 'error' => 'Arquivo de comprovante inválido.'];
        }

        if ($size > 5 * 1024 * 1024) {
            return ['path' => null, 'error' => 'O comprovante deve ter no máximo 5 MB.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $extensions = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];

        if (!isset($extensions[$mime])) {
            return ['path' => null, 'error' => 'Use um comprovante em PDF, PNG ou JPEG.'];
        }

        $directory = ROOT_PATH . '/storage/comprovantes';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return ['path' => null, 'error' => 'Não foi possível salvar o comprovante.'];
        }

        $filename = $eventoId . '-' . $userId . '-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        $destination = $directory . '/' . $filename;
        if (!move_uploaded_file($tmp, $destination)) {
            return ['path' => null, 'error' => 'Não foi possível salvar o comprovante.'];
        }

        return ['path' => 'storage/comprovantes/' . $filename, 'error' => null];
    }
}
