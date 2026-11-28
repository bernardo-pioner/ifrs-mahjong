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

        $dataReferencia = "COALESCE(NULLIF(e.data_fim, ''), NULLIF(e.data_inicio, ''))";
        $eventoAberto = "({$dataReferencia} IS NULL OR date({$dataReferencia}) > date('now', 'localtime', '-1 day'))";

        if ($inscricao === 'pendente') {
            $sql .= " AND ep.status = 'pendente' AND {$eventoAberto}";
        } elseif ($inscricao === 'inscrito') {
            $sql .= " AND ep.status = 'aprovado' AND {$eventoAberto}";
        } elseif ($inscricao === 'nao_inscrito') {
            $sql .= " AND ep.evento_id IS NULL AND {$eventoAberto}";
        }

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
        $eventos = $stmt->fetchAll();
        Event::attachDays($eventos);
        foreach ($eventos as &$evento) {
            $proxima = Event::dataMaisProxima($evento);
            if ($proxima !== null) {
                $evento['data_inicio'] = $proxima;
            }
        }
        unset($evento);
        usort($eventos, static function (array $a, array $b): int {
            $aData = (string) ($a['data_inicio'] ?? '');
            $bData = (string) ($b['data_inicio'] ?? '');
            if ($aData === $bData) {
                return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
            }
            if ($aData === '') {
                return 1;
            }
            if ($bData === '') {
                return -1;
            }

            return $aData <=> $bData;
        });
        $totalEventos = (int) db()->query('SELECT COUNT(*) FROM eventos')->fetchColumn();

        $this->view('events/index', [
            'title' => 'Eventos',
            'user' => $user,
            'currentPath' => '/eventos',
            'eventos' => $eventos,
            'busca' => $busca,
            'tipoFiltro' => $tipo,
            'inscricaoFiltro' => $inscricao,
            'situacaoFiltro' => $situacao,
            'clubeSemEventos' => $totalEventos === 0,
            'publicarAvisos' => !empty($_SESSION['eventos_avisar']),
            'enviarEmailsAvisos' => Notification::hasPendingEmails(),
        ]);
    }

    public function publishNotices(): void
    {
        $this->requireAuth();
        verifyCsrf();
        unset($_SESSION['eventos_avisar']);
        session_write_close();
        Notification::publishActive();
        $this->json(['ok' => true]);
    }

    public function sendNoticeEmails(): void
    {
        $this->requireAuth();
        verifyCsrf();
        session_write_close();
        Notification::sendPendingEmails();
        $this->json(['ok' => true]);
    }

    private function json(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
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
        [$errors, $parsed] = $this->validateEventForm($old, true, true);

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

        $bases = $this->basesDoFormulario($user, $parsed);
        Event::createMany($bases);

        if ($parsed['recorrente']) {
            flash('success', 'Série de ' . count($bases) . ' eventos criada com sucesso.');
        } else {
            flash('success', 'Evento criado com sucesso.');
        }

        $_SESSION['eventos_avisar'] = 1;

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

        if (Event::isEncerrado($evento)) {
            flash('error', 'Eventos encerrados não podem ser editados.');
            redirect('/eventos/' . (int) $evento['id']);
            return;
        }

        $inicio = !empty($evento['data_inicio'])
            ? new DateTimeImmutable($evento['data_inicio'])
            : null;

        $details = Event::typeDetails($evento);
        $dias = $this->diasParaFormulario($evento);
        if ($dias === []) {
            $fim = !empty($evento['data_fim'])
                ? new DateTimeImmutable($evento['data_fim'])
                : null;
            $dias = [[
                'data' => $inicio?->format('Y-m-d') ?? '',
                'inicio' => $inicio?->format('H:i') ?? '14:00',
                'fim' => $fim !== null && $inicio !== null && $fim->format('Y-m-d') === $inicio->format('Y-m-d')
                    && $fim->format('H:i') > $inicio->format('H:i')
                    ? $fim->format('H:i')
                    : '',
            ]];
        }

        $old = [
            'titulo' => $evento['titulo'],
            'tipo' => $evento['tipo'],
            'modalidade' => (string) ($evento['modalidade'] ?? 'livre'),
            'data' => $inicio?->format('Y-m-d') ?? '',
            'horario' => $inicio?->format('H:i') ?? '14:00',
            'dias' => $dias,
            'local' => $evento['local'] ?? '',
            'descricao' => $evento['descricao'] ?? '',
            'tem_limite' => $details['limite'] !== null ? '1' : '0',
            'limite' => $details['limite'] !== null ? (string) $details['limite'] : '',
            'tem_pagamento' => $details['taxa'] > 0 ? '1' : '0',
            'taxa' => $details['taxa'] > 0
                ? number_format($details['taxa'], 2, '.', '')
                : '',
            'tem_premiacao' => ($details['premiacao'] ?? []) !== [] ? '1' : '0',
            'premiacao_qtd' => ($details['premiacao'] ?? []) !== []
                ? (string) count($details['premiacao'])
                : '3',
            'premiacao' => $details['premiacao'] ?? [],
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

        if (Event::isEncerrado($evento)) {
            flash('error', 'Eventos encerrados não podem ser editados.');
            redirect('/eventos/' . (int) $evento['id']);
            return;
        }

        $old = $this->collectFormInput();
        [$errors, $parsed] = $this->validateEventForm($old, false, true);

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

        $escopo = request('aplicar_serie', 'atual');
        if (!in_array($escopo, ['atual', 'seguintes', 'ativas'], true)) {
            $escopo = 'atual';
        }

        $serieId = isset($evento['serie_id']) ? (int) $evento['serie_id'] : null;
        $dataInicio = isset($evento['data_inicio']) ? (string) $evento['data_inicio'] : null;
        if ($escopo === 'seguintes') {
            $alvos = Event::futureInSeries($serieId, $dataInicio);
        } elseif ($escopo === 'ativas') {
            $alvos = Event::activeInSeries($serieId);
        } else {
            $alvos = [];
        }

        $salvarAtual = $escopo !== 'ativas' || Event::isEmAndamento($evento);
        $pdo->beginTransaction();

        try {
            if ($salvarAtual) {
                $this->saveEventEdits($evento, $parsed, $parsed['dias']);
            }

            foreach ($alvos as $alvo) {
                if ((int) $alvo['id'] === (int) $evento['id'] || Event::isEncerrado($alvo)) {
                    continue;
                }
                $delta = $this->deltaDias(
                    (string) $evento['data_inicio'],
                    (string) ($alvo['data_inicio'] ?? '')
                );
                $dias = $this->deslocarDias($parsed['dias'], $delta);
                if ($dias === []) {
                    continue;
                }
                $this->saveEventEdits($alvo, $parsed, $dias);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        if ($escopo === 'ativas') {
            $mensagem = 'As ocorrências em andamento foram atualizadas.';
        } elseif ($escopo === 'seguintes' && $alvos !== []) {
            $mensagem = 'Evento atualizado. As ocorrências futuras também foram atualizadas.';
        } else {
            $mensagem = 'Evento atualizado com sucesso.';
        }

        flash('success', $mensagem);
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

        $evento['dias'] = Event::daysFor((int) $evento['id']);
        $proxima = Event::dataMaisProxima($evento);
        if ($proxima !== null) {
            $evento['data_inicio'] = $proxima;
        }

        $details = Event::typeDetails($evento);
        $participantes = Event::listParticipants((int) $id, (int) $user['id']);
        $minhaParticipacao = Event::findParticipation((int) $id, (int) $user['id']);
        $meuPagamento = Event::findPayment((int) $id, (int) $user['id']);
        $vagasOcupadas = Event::countActiveParticipants((int) $id);
        $temVaga = Event::hasCapacity($evento);
        $ehAdmin = ($user['role'] ?? '') === 'admin';
        $userId = (int) $user['id'];

        $levelLabels = levelLabels();
        $organizador = null;
        $adminId = (int) ($evento['admin_id'] ?? 0);
        if ($adminId > 0) {
            $organizerUser = User::findById($adminId);
            if ($organizerUser) {
                $organizerUsername = trim((string) ($organizerUser['username'] ?? ''));
                $organizerNickname = trim((string) ($organizerUser['nickname'] ?? ''));
                if ($organizerNickname === '') {
                    $organizerNickname = $organizerUsername;
                }
                $organizerName = trim((string) ($organizerUser['name'] ?? ''));
                $organizerLevel = (string) ($organizerUser['level'] ?? '');
                $organizador = [
                    'nickname' => $organizerNickname,
                    'username' => $organizerUsername,
                    'name' => $organizerName,
                    'label' => $organizerName !== ''
                        ? $organizerName
                        : ($organizerNickname !== '' ? $organizerNickname : 'Organizador'),
                    'bio' => trim((string) ($organizerUser['bio'] ?? '')),
                    'mahjong_soul' => trim((string) ($organizerUser['mahjong_soul'] ?? '')),
                    'riichi_city' => trim((string) ($organizerUser['riichi_city'] ?? '')),
                    'discord' => trim((string) ($organizerUser['discord'] ?? '')),
                    'photo_url' => userPhotoUrl($organizerUser),
                    'member_since' => formatMemberSince($organizerUser['created_at'] ?? null),
                    'level_label' => $levelLabels[$organizerLevel] ?? '',
                ];
            }
        }

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
            'organizador' => $organizador,
            'partidas' => in_array($evento['tipo'], ['encontro', 'oficina'], true)
                ? GameMatch::forEvent((int) $id)
                : [],
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

    public function unregister(string $id): void
    {
        $user = $this->requireAuth();
        verifyCsrf();

        $evento = Event::findById((int) $id);
        if (!$evento) {
            flash('error', 'Evento não encontrado.');
            redirect('/eventos');
            return;
        }

        $result = Event::unregister($evento, (int) $user['id']);
        if (!$result['ok']) {
            flash('error', (string) $result['error']);
            redirect('/eventos/' . (int) $id);
            return;
        }

        flash('success', 'Inscrição cancelada.');
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

        $suplente = request('suplente');
        if ($suplente === '0' || $suplente === '1') {
            $result = Event::setParticipantSuplente(
                (int) $id,
                (int) $participanteId,
                $suplente === '1'
            );
            $mensagem = $suplente === '1'
                ? 'Participante marcado como suplente.'
                : 'Suplente removido.';
        } else {
            $status = request('status');
            $result = Event::updateParticipantStatus((int) $id, (int) $participanteId, $status);
            $mensagem = 'Status da participação atualizado.';
        }

        if (!$result['ok']) {
            flash('error', (string) $result['error']);
        } else {
            flash('success', $mensagem);
        }

        redirect('/eventos/' . (int) $id);
    }

    public function removeParticipant(string $id, string $participanteId): void
    {
        $this->requireAdmin();
        verifyCsrf();

        $result = Event::removeParticipant((int) $id, (int) $participanteId);
        if (!$result['ok']) {
            flash('error', (string) $result['error']);
            redirect('/eventos/' . (int) $id);
            return;
        }

        foreach ($result['files'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        flash('success', 'Inscrição removida.');
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

        $escopo = request('excluir_serie', 'atual');
        if (!in_array($escopo, ['atual', 'seguintes', 'ativas'], true)) {
            $escopo = 'atual';
        }

        $serieId = isset($evento['serie_id']) ? (int) $evento['serie_id'] : null;
        $dataInicio = isset($evento['data_inicio']) ? (string) $evento['data_inicio'] : null;
        $incluirAtual = true;

        if ($escopo === 'ativas') {
            $alvos = Event::activeInSeries($serieId);
            $incluirAtual = false;
        } elseif ($escopo === 'seguintes') {
            $alvos = Event::futureInSeries($serieId, $dataInicio);
        } else {
            $alvos = [];
        }

        if ($incluirAtual && Event::hasLinkedRecords($eventoId)) {
            flash(
                'error',
                'Este evento possui partidas ou rankings vinculados e não pode ser excluído.'
            );
            redirect('/eventos/' . $eventoId);
            return;
        }

        $excluidas = 0;
        $mantidas = 0;
        $excluiuAtual = false;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('DELETE FROM eventos WHERE id = :id');
            foreach ($alvos as $alvo) {
                $alvoId = (int) $alvo['id'];
                if (Event::hasLinkedRecords($alvoId)) {
                    $mantidas++;
                    continue;
                }
                $stmt->execute([':id' => $alvoId]);
                if ($alvoId === $eventoId) {
                    $excluiuAtual = true;
                }
                $excluidas++;
            }
            if ($incluirAtual) {
                $stmt->execute([':id' => $eventoId]);
                $excluiuAtual = true;
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        flash('success', $this->mensagemExclusao($escopo, $excluidas, $mantidas, $excluiuAtual));
        redirect('/eventos');
    }

    /**
     * @param array<string, mixed> $evento
     * @param array<string, mixed> $parsed
     */
    /**
     * @param list<array{inicio: DateTimeImmutable, fim: ?DateTimeImmutable}> $dias
     */
    private function saveEventEdits(array $evento, array $parsed, array $dias): void
    {
        $pdo = db();
        $eventoId = (int) $evento['id'];
        $stmt = $pdo->prepare(
            'UPDATE eventos
             SET titulo = :titulo,
                 tipo = :tipo,
                 modalidade = :modalidade,
                 descricao = :descricao,
                 local = :local,
                 atualizado_em = datetime(\'now\')
             WHERE id = :id'
        );
        $stmt->execute([
            ':titulo' => $parsed['titulo'],
            ':tipo' => $parsed['tipo'],
            ':modalidade' => $parsed['modalidade'],
            ':descricao' => $parsed['descricao'],
            ':local' => $parsed['local'],
            ':id' => $eventoId,
        ]);
        Event::replaceDays($eventoId, $dias);

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
            $stmt->execute([':evento_id' => $eventoId]);
            Event::insertTypeRow(
                $eventoId,
                $parsed['tipo'],
                $parsed['limite'],
                $parsed['taxa'],
                $parsed['premiacao']
            );
            return;
        }

        if ($parsed['tipo'] === 'torneio') {
            $stmt = $pdo->prepare(
                'UPDATE torneios
                 SET maximo_participantes = :limite,
                     taxa_participacao = :taxa,
                     premiacao = :premiacao
                 WHERE evento_id = :id'
            );
            $stmt->execute([
                ':limite' => $parsed['limite'],
                ':taxa' => $parsed['taxa'],
                ':premiacao' => Event::encodePremiacao($parsed['premiacao']),
                ':id' => $eventoId,
            ]);
            return;
        }

        if ($parsed['tipo'] === 'oficina') {
            $stmt = $pdo->prepare(
                'UPDATE oficinas
                 SET vagas = :limite,
                     taxa_inscricao = :taxa,
                     premiacao = :premiacao
                 WHERE evento_id = :id'
            );
            $stmt->execute([
                ':limite' => $parsed['limite'],
                ':taxa' => $parsed['taxa'],
                ':premiacao' => Event::encodePremiacao($parsed['premiacao']),
                ':id' => $eventoId,
            ]);
            return;
        }
    }

    private function deltaDias(string $inicioOriginal, string $inicioOcorrencia): int
    {
        if ($inicioOriginal === '' || $inicioOcorrencia === '') {
            return 0;
        }

        try {
            $diaOriginal = (new DateTimeImmutable($inicioOriginal))->setTime(0, 0);
            $diaOcorrencia = (new DateTimeImmutable($inicioOcorrencia))->setTime(0, 0);
        } catch (Exception) {
            return 0;
        }

        return (int) $diaOriginal->diff($diaOcorrencia)->format('%r%a');
    }

    /**
     * @param list<array{inicio?: mixed, fim?: mixed}> $dias
     * @return list<array{inicio: DateTimeImmutable, fim: ?DateTimeImmutable}>
     */
    private function deslocarDias(array $dias, int $delta): array
    {
        $resultado = [];
        foreach ($dias as $dia) {
            if (!is_array($dia) || !$dia['inicio'] instanceof DateTimeImmutable) {
                continue;
            }
            $inicio = $delta === 0 ? $dia['inicio'] : $dia['inicio']->modify(sprintf('%+d days', $delta));
            $fim = $dia['fim'] instanceof DateTimeImmutable ? $dia['fim'] : null;
            if ($fim !== null && $delta !== 0) {
                $fim = $fim->modify(sprintf('%+d days', $delta));
            }
            $resultado[] = [
                'inicio' => $inicio,
                'fim' => $fim,
            ];
        }

        return $resultado;
    }

    /** @return list<array{data: string, inicio: string, fim: string}> */
    private function diasParaFormulario(array $evento): array
    {
        $dias = [];
        foreach (Event::daysFor((int) $evento['id']) as $dia) {
            try {
                $inicio = new DateTimeImmutable((string) $dia['data_inicio']);
            } catch (Exception) {
                continue;
            }
            $fimTexto = '';
            if (!empty($dia['data_fim'])) {
                try {
                    $fim = new DateTimeImmutable((string) $dia['data_fim']);
                    if ($fim->format('Y-m-d') === $inicio->format('Y-m-d') && $fim > $inicio) {
                        $fimTexto = $fim->format('H:i');
                    }
                } catch (Exception) {
                    $fimTexto = '';
                }
            }
            $dias[] = [
                'data' => $inicio->format('Y-m-d'),
                'inicio' => $inicio->format('H:i'),
                'fim' => $fimTexto,
            ];
        }

        return $dias;
    }

    private function mensagemExclusao(string $escopo, int $excluidas, int $mantidas, bool $excluiuAtual): string
    {
        if ($escopo === 'ativas') {
            if ($excluidas === 1) {
                $mensagem = '1 ocorrência em andamento foi excluída.';
            } elseif ($excluidas > 1) {
                $mensagem = $excluidas . ' ocorrências em andamento foram excluídas.';
            } else {
                $mensagem = 'Nenhuma ocorrência em andamento foi excluída.';
            }

            if ($mantidas === 1) {
                $mensagem .= ' 1 ocorrência em andamento não pôde ser excluída porque tem partidas ou rankings.';
            } elseif ($mantidas > 1) {
                $mensagem .= ' ' . $mantidas . ' ocorrências em andamento não puderam ser excluídas porque têm partidas ou rankings.';
            }

            return $mensagem;
        }

        if (!$excluiuAtual) {
            $mensagem = 'Evento excluído com sucesso.';
        } elseif ($escopo === 'seguintes' && $excluidas === 1) {
            $mensagem = 'Evento excluído. 1 ocorrência futura também foi excluída.';
        } elseif ($escopo === 'seguintes' && $excluidas > 1) {
            $mensagem = 'Evento excluído. ' . $excluidas . ' ocorrências futuras também foram excluídas.';
        } else {
            $mensagem = 'Evento excluído com sucesso.';
        }

        if ($mantidas === 1) {
            $mensagem .= ' 1 ocorrência futura não pôde ser excluída porque tem partidas ou rankings.';
        } elseif ($mantidas > 1) {
            $mensagem .= ' ' . $mantidas . ' ocorrências futuras não puderam ser excluídas porque têm partidas ou rankings.';
        }

        return $mensagem;
    }

    /** @return array<string, mixed> */
    private function collectFormInput(): array
    {
        $dias = $this->collectDiasInput();
        $primeiro = $dias[0];

        return [
            'titulo' => request('titulo'),
            'tipo' => request('tipo', 'encontro'),
            'modalidade' => request('modalidade', 'livre'),
            'data' => $primeiro['data'],
            'horario' => $primeiro['inicio'],
            'dias' => $dias,
            'local' => request('local'),
            'tem_limite' => isset($_POST['tem_limite']) ? '1' : '0',
            'limite' => request('limite'),
            'tem_pagamento' => isset($_POST['tem_pagamento']) ? '1' : '0',
            'taxa' => request('taxa'),
            'tem_premiacao' => isset($_POST['tem_premiacao']) ? '1' : '0',
            'premiacao_qtd' => request('premiacao_qtd', '3'),
            'premiacao' => self::collectPremiacaoInput(),
            'descricao' => request('descricao'),
            'recorrente' => isset($_POST['recorrente']) ? '1' : '0',
            'recorrencia_dias' => request('recorrencia_dias', '7'),
            'recorrencia_ocorrencias' => request('recorrencia_ocorrencias', '4'),
        ];
    }

    /** @return list<array{data: string, inicio: string, fim: string}> */
    private function collectDiasInput(): array
    {
        $raw = $_POST['dias'] ?? null;
        $dias = [];
        if (is_array($raw)) {
            foreach ($raw as $dia) {
                if (!is_array($dia)) {
                    continue;
                }
                $dias[] = [
                    'data' => trim((string) ($dia['data'] ?? '')),
                    'inicio' => trim((string) ($dia['inicio'] ?? '')),
                    'fim' => trim((string) ($dia['fim'] ?? '')),
                ];
            }
        }

        if ($dias === []) {
            $dias[] = [
                'data' => request('data'),
                'inicio' => request('horario', '14:00'),
                'fim' => '',
            ];
        }

        return array_slice($dias, 0, 31);
    }

    /** @return list<string> */
    private function collectPremiacaoInput(): array
    {
        $raw = $_POST['premiacao'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $itens = [];
        foreach ($raw as $item) {
            if (!is_string($item)) {
                continue;
            }
            $itens[] = trim($item);
        }

        return array_slice($itens, 0, 10);
    }

    /**
     * @param array<string, mixed> $old
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    private function validateEventForm(array $old, bool $allowRecurrence, bool $bloquearDataPassada = false): array
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

        $diasInformados = $old['dias'] ?? null;
        if (!is_array($diasInformados) || $diasInformados === []) {
            $diasInformados = [[
                'data' => (string) ($old['data'] ?? ''),
                'inicio' => (string) ($old['horario'] ?? ''),
                'fim' => '',
            ]];
        }
        if (count($diasInformados) > 31) {
            $errors[] = 'É possível informar no máximo 31 dias.';
            $diasInformados = array_slice($diasInformados, 0, 31);
        }

        $dias = [];
        $iniciosUsados = [];
        $dataAnterior = null;
        foreach (array_values($diasInformados) as $indice => $dia) {
            if (!is_array($dia)) {
                continue;
            }
            $numero = $indice + 1;
            $rotulo = $numero === 1 ? 'do primeiro dia' : 'do ' . $numero . 'º dia';
            $data = trim((string) ($dia['data'] ?? ''));
            $horaInicio = $this->horarioValido((string) ($dia['inicio'] ?? ''));
            $horaFimBruta = trim((string) ($dia['fim'] ?? ''));
            $horaFim = $horaFimBruta === '' ? null : $this->horarioValido($horaFimBruta);

            $diaData = false;
            if ($data !== '') {
                $diaData = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
                $errosData = DateTimeImmutable::getLastErrors();
                if (
                    !$diaData ||
                    ($errosData !== false && ($errosData['warning_count'] > 0 || $errosData['error_count'] > 0)) ||
                    $diaData->format('Y-m-d') !== $data
                ) {
                    $diaData = false;
                }
            }

            if ($diaData === false || $horaInicio === null) {
                $errors[] = 'Informe uma data e uma hora de início válidas ' . $rotulo . '.';
                continue;
            }

            if ($bloquearDataPassada && $data < (new DateTimeImmutable('today'))->format('Y-m-d')) {
                $errors[] = 'A data ' . $rotulo . ' não pode ser anterior a hoje.';
            }

            $inicioDia = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $data . ' ' . $horaInicio);
            if (!$inicioDia) {
                $errors[] = 'Informe uma data e uma hora de início válidas ' . $rotulo . '.';
                continue;
            }

            $fimDia = null;
            if ($horaFimBruta !== '' && $horaFim === null) {
                $errors[] = 'Informe uma hora de fim válida ' . $rotulo . '.';
            } elseif ($horaFim !== null) {
                $fimDia = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $data . ' ' . $horaFim);
                if (!$fimDia || $fimDia <= $inicioDia) {
                    $errors[] = 'A hora de fim deve ser posterior à hora de início ' . $rotulo . '.';
                    $fimDia = null;
                }
            }

            if ($dataAnterior !== null && $data <= $dataAnterior) {
                $errors[] = 'A data ' . $rotulo . ' deve ser pelo menos um dia depois do dia anterior.';
            }
            $dataAnterior = $data;

            $chaveInicio = $inicioDia->format('Y-m-d H:i');
            if (isset($iniciosUsados[$chaveInicio])) {
                $errors[] = 'Não repita a mesma data e hora de início.';
            }
            $iniciosUsados[$chaveInicio] = true;

            $dias[] = [
                'inicio' => $inicioDia,
                'fim' => $fimDia,
            ];
        }

        if ($dias === []) {
            $inicio = null;
            $fim = null;
        } else {
            $inicio = $dias[0]['inicio'];
            $fim = $dias[0]['fim'];
        }

        if (mb_strlen($local) > 200) {
            $errors[] = 'O local deve ter no máximo 200 caracteres.';
        }

        if (mb_strlen($descricao) > 2500) {
            $errors[] = 'A descrição deve ter no máximo 2.500 caracteres.';
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

        $premiacao = [];
        if (in_array($tipo, ['torneio', 'oficina'], true) && ($old['tem_premiacao'] ?? '0') === '1') {
            $qtd = filter_var($old['premiacao_qtd'] ?? '', FILTER_VALIDATE_INT);
            if ($qtd === false || $qtd < 1 || $qtd > 10) {
                $errors[] = 'Informe entre 1 e 10 colocações para a premiação.';
                $qtd = 0;
            }

            $itensRaw = $old['premiacao'] ?? [];
            if (!is_array($itensRaw)) {
                $itensRaw = [];
            }

            for ($i = 0; $i < $qtd; $i++) {
                $texto = trim((string) ($itensRaw[$i] ?? ''));
                if ($texto === '') {
                    $errors[] = 'Informe a premiação do ' . ($i + 1) . 'º lugar.';
                } elseif (mb_strlen($texto) > 200) {
                    $errors[] = 'A premiação do ' . ($i + 1) . 'º lugar deve ter no máximo 200 caracteres.';
                } else {
                    $premiacao[] = $texto;
                }
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
            'dias' => $dias,
            'local' => $local !== '' ? $local : null,
            'descricao' => $descricao !== '' ? $descricao : null,
            'limite' => $limite,
            'taxa' => $taxa,
            'premiacao' => $premiacao,
            'recorrente' => $recorrente,
            'recorrencia_dias' => (int) $recorrenciaDias,
            'recorrencia_ocorrencias' => (int) $recorrenciaOcorrencias,
        ];

        return [$errors, $parsed];
    }

    /** @param array<string, mixed> $user */
    /** @param array<string, mixed> $parsed */
    /** @return list<array<string, mixed>> */
    private function basesDoFormulario(array $user, array $parsed): array
    {
        $dias = $parsed['dias'];
        if (!is_array($dias) || $dias === []) {
            return [];
        }

        $ocorrencias = [$dias];
        if ($parsed['recorrente']) {
            $ocorrencias = [];
            $intervalo = (int) $parsed['recorrencia_dias'];
            for ($i = 0; $i < (int) $parsed['recorrencia_ocorrencias']; $i++) {
                $deslocados = $this->deslocarDias($dias, $intervalo * $i);
                if ($deslocados !== []) {
                    $ocorrencias[] = $deslocados;
                }
            }
        }

        $bases = [];
        foreach ($ocorrencias as $diasOcorrencia) {
            $primeiro = $diasOcorrencia[0]['inicio'] ?? null;
            if (!$primeiro instanceof DateTimeImmutable) {
                continue;
            }
            $bases[] = [
                'admin_id' => (int) $user['id'],
                'tipo' => $parsed['tipo'],
                'modalidade' => $parsed['modalidade'],
                'titulo' => $parsed['titulo'],
                'descricao' => $parsed['descricao'],
                'data_inicio' => $primeiro->format('Y-m-d H:i:s'),
                'data_fim' => null,
                'local' => $parsed['local'],
                'limite' => $parsed['limite'],
                'taxa' => $parsed['taxa'],
                'premiacao' => $parsed['premiacao'],
                'dias' => $diasOcorrencia,
            ];
        }

        return $bases;
    }

    private function horarioValido(string $valor): ?string
    {
        if (preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', trim($valor), $partes) !== 1) {
            return null;
        }

        $hora = (int) $partes[1];
        $minuto = (int) $partes[2];
        if ($hora > 23 || $minuto > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hora, $minuto);
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
