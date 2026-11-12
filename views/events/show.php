<?php

$tiposEvento = [
    'encontro' => 'Encontro casual',
    'oficina' => 'Oficina',
    'torneio' => 'Torneio',
];

$statusLabels = [
    'pendente' => 'Pendente',
    'aprovado' => 'Aprovado',
    'cancelado' => 'Cancelado',
];

$formatarData = static function (?string $data): string {
    if (!$data) {
        return 'Data a definir';
    }

    try {
        return (new DateTimeImmutable($data))->format('d/m/Y · H:i');
    } catch (Exception) {
        return $data;
    }
};

$ehAdmin = ($user['role'] ?? '') === 'admin';
$eventoId = (int) $evento['id'];
$userId = (int) ($user['id'] ?? 0);
$taxa = (float) ($details['taxa'] ?? 0);
$limite = $details['limite'] ?? null;
$inscricoesAbertas = (int) ($evento['inscricoes_abertas'] ?? 0) === 1;
$precisaPagamento = in_array($evento['tipo'], ['torneio', 'oficina'], true) && $taxa > 0;
$jaInscrito = $minhaParticipacao !== null && ($minhaParticipacao['status'] ?? '') !== 'cancelado';
$statusInscricao = (string) ($minhaParticipacao['status'] ?? '');
$podeInscrever = !$ehAdmin && $inscricoesAbertas && !$jaInscrito && $temVaga;
$meuPagamento = $meuPagamento ?? null;
$temComprovante = is_array($meuPagamento) && !empty($meuPagamento['comprovante']);
$podeEnviarComprovante = !$ehAdmin
    && $jaInscrito
    && $precisaPagamento
    && $statusInscricao === 'pendente';

?>

<section class="event-form-page event-show-page">
    <header class="event-form-header">
        <a class="event-back-link" href="/eventos">
            ← Voltar para eventos
        </a>
        <h1><?= e($evento['titulo']) ?></h1>
    </header>

    <article class="event-form-card">
        <div class="event-card-top">
            <span class="event-type">
                <?= e($tiposEvento[$evento['tipo']] ?? 'Outro') ?>
            </span>
            <span class="event-modality">
                <?= e(eventModalidadeLabel($evento['modalidade'] ?? null)) ?>
            </span>
        </div>

        <div class="event-details">
            <div class="event-detail">
                <span>
                    <strong>Início:</strong>
                    <?= e($formatarData($evento['data_inicio'])) ?>

                    <br>

                    <strong>Término:</strong>
                    <?= e(!empty($evento['data_fim']) ? $formatarData($evento['data_fim']) : 'Não informado') ?>
                </span>
            </div>

            <?php if (!empty($evento['local'])): ?>
                <div class="event-detail">
                    <span>
                        <strong>Local:</strong>
                        <?= e($evento['local']) ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="event-detail">
                <span>
                    <strong>Organizador:</strong>
                    <?= e($evento['organizador'] ?? 'Clube de Mahjong') ?>
                </span>
            </div>

            <?php if ($limite !== null): ?>
                <div class="event-detail">
                    <span>
                        <strong>Vagas aprovadas:</strong>
                        <?= (int) $vagasOcupadas ?> / <?= (int) $limite ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($precisaPagamento): ?>
                <div class="event-detail">
                    <span>
                        <strong>Taxa:</strong>
                        R$ <?= e(number_format($taxa, 2, ',', '.')) ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($evento['descricao'])): ?>
            <div class="form-field">
                <h2>Descrição</h2>
                <p><?= nl2br(e($evento['descricao'])) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$ehAdmin): ?>
            <div class="event-form-divider"></div>

            <section class="event-registration-box">
                <h2>Sua inscrição</h2>

                <?php if ($jaInscrito): ?>
                    <p>
                        Status:
                        <strong class="event-participant-status is-<?= e($statusInscricao) ?>">
                            <?= e($statusLabels[$statusInscricao] ?? $statusInscricao) ?>
                        </strong>
                    </p>

                    <?php if ($precisaPagamento): ?>
                        <?php if ($temComprovante): ?>
                            <p>
                                Comprovante enviado.
                                <a
                                    href="/eventos/<?= $eventoId ?>/comprovantes/<?= $userId ?>"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    Ver comprovante
                                </a>
                            </p>
                        <?php elseif ($statusInscricao === 'pendente'): ?>
                            <p>
                                Sua inscrição está pendente. Envie o comprovante de
                                <strong>R$ <?= e(number_format($taxa, 2, ',', '.')) ?></strong>
                                para o administrador analisar.
                            </p>
                        <?php endif; ?>

                        <?php if ($podeEnviarComprovante): ?>
                            <form
                                method="post"
                                action="/eventos/<?= $eventoId ?>/comprovante"
                                enctype="multipart/form-data"
                                class="event-register-form"
                            >
                                <?= csrfField() ?>

                                <div class="form-field">
                                    <span class="file-field-label">
                                        <?= $temComprovante
                                            ? 'Substituir comprovante (PDF, PNG ou JPEG)'
                                            : 'Enviar comprovante (PDF, PNG ou JPEG)' ?>
                                    </span>
                                    <label class="file-picker" for="comprovante">
                                        <input
                                            id="comprovante"
                                            name="comprovante"
                                            type="file"
                                            accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg"
                                            required
                                            data-file-picker
                                        >
                                        <span class="file-picker-button">Escolher arquivo</span>
                                        <span class="file-picker-name" data-file-name>
                                            Nenhum arquivo selecionado
                                        </span>
                                    </label>
                                </div>

                                <button class="button button-primary" type="submit">
                                    <?= $temComprovante ? 'Atualizar comprovante' : 'Enviar comprovante' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($statusInscricao === 'pendente'): ?>
                        <p>Aguarde a aprovação do administrador.</p>
                    <?php endif; ?>
                <?php elseif (!$inscricoesAbertas): ?>
                    <p>As inscrições para este evento estão fechadas.</p>
                <?php elseif (!$temVaga): ?>
                    <p>Não há mais vagas disponíveis neste evento.</p>
                <?php elseif ($podeInscrever): ?>
                    <form
                        method="post"
                        action="/eventos/<?= $eventoId ?>/inscrever"
                        class="event-register-form"
                    >
                        <?= csrfField() ?>

                        <?php if ($precisaPagamento): ?>
                            <p>
                                Este evento exige pagamento de
                                <strong>R$ <?= e(number_format($taxa, 2, ',', '.')) ?></strong>.
                                Ao se inscrever, o status ficará pendente e você poderá
                                enviar o comprovante nesta página.
                            </p>
                        <?php else: ?>
                            <p>Confirme sua inscrição neste evento.</p>
                        <?php endif; ?>

                        <button class="button button-primary" type="submit">
                            Inscrever-se
                        </button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <div class="event-form-divider"></div>

        <section class="event-participants">
            <h2>Participantes inscritos (<?= count($participantes ?? []) ?>)</h2>

            <?php if (empty($participantes)): ?>
                <p class="event-participants-empty">
                    Nenhum participante inscrito neste evento.
                </p>
            <?php else: ?>
                <div class="event-participants-list">
                    <?php foreach ($participantes as $participante): ?>
                        <?php
                        $username = trim((string) ($participante['username'] ?? ''));
                        $nickname = trim((string) ($participante['apelido'] ?? ''));
                        if ($nickname === '') {
                            $nickname = $username !== '' ? $username : 'Participante';
                        }
                        $displayName = trim((string) ($participante['nome'] ?? ''));
                        if ($displayName === '') {
                            $displayName = $nickname;
                        }
                        $bio = trim((string) ($participante['bio'] ?? ''));
                        $photoUrl = (string) ($participante['photo_url'] ?? '');
                        $memberSince = (string) ($participante['member_since'] ?? '');
                        $levelLabel = (string) ($participante['level_label'] ?? '');
                        ?>
                        <div class="event-participant">
                            <div>
                                <button
                                    type="button"
                                    class="event-participant-name"
                                    data-open-bio
                                    data-nickname="<?= e($nickname) ?>"
                                    data-username="<?= e($username) ?>"
                                    data-real-name="<?= e((string) ($participante['nome'] ?? '')) ?>"
                                    data-bio="<?= e($bio) ?>"
                                    data-photo="<?= e($photoUrl) ?>"
                                    data-member-since="<?= e($memberSince) ?>"
                                    data-level="<?= e($levelLabel) ?>"
                                >
                                    <?= e($displayName) ?>
                                    <?php if ($nickname !== '' && $nickname !== $displayName): ?>
                                        <small>(<?= e($nickname) ?>)</small>
                                    <?php endif; ?>
                                </button>

                                <div class="event-participant-meta">
                                    <span class="event-participant-status is-<?= e($participante['status']) ?>">
                                        <?= e($statusLabels[$participante['status']] ?? $participante['status']) ?>
                                    </span>

                                    <?php if (!empty($participante['pode_ver_comprovante'])): ?>
                                        <a
                                            href="/eventos/<?= $eventoId ?>/comprovantes/<?= (int) $participante['usuario_id'] ?>"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            Ver comprovante
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($ehAdmin && $participante['status'] === 'pendente'): ?>
                                <form
                                    method="post"
                                    action="/eventos/<?= $eventoId ?>/participantes/<?= (int) $participante['usuario_id'] ?>/status"
                                    class="event-status-form"
                                >
                                    <?= csrfField() ?>
                                    <input type="hidden" name="status" value="aprovado">
                                    <button class="button button-primary" type="submit">
                                        Aprovar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($ehAdmin): ?>
            <div class="event-form-divider"></div>

            <div class="event-form-actions">
                <a
                    class="button button-secondary"
                    href="/eventos/<?= $eventoId ?>/editar"
                >
                    Editar evento
                </a>

                <form
                    method="post"
                    action="/eventos/<?= $eventoId ?>/excluir"
                    onsubmit="return confirm('Tem certeza que deseja excluir este evento? Esta ação não poderá ser desfeita.');"
                >
                    <?= csrfField() ?>

                    <button
                        class="button button-secondary"
                        type="submit"
                    >
                        Excluir evento
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </article>

    <div
        id="participant-bio-modal"
        class="user-details-modal"
        hidden
        aria-hidden="true"
    >
        <div class="user-details-backdrop" data-close-bio></div>
        <div
            class="user-details-dialog profile-card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="participant-bio-title"
        >
            <button
                type="button"
                class="user-details-close"
                aria-label="Fechar bio"
                data-close-bio
            >
                ×
            </button>

            <div class="profile-photo-field user-details-header">
                <span class="profile-photo-preview" aria-hidden="true">
                    <img id="participant-bio-photo" src="" alt="" hidden>
                    <span class="icon icon-user" id="participant-bio-photo-fallback"></span>
                </span>
                <div class="user-details-heading">
                    <h2 id="participant-bio-title"></h2>
                    <p id="participant-bio-username" class="lede"></p>
                    <p id="participant-bio-member-since" class="member-since"></p>
                </div>
            </div>

            <div class="profile-current-data">
                <div>
                    <span class="profile-label">Nome</span>
                    <p id="participant-bio-name"></p>
                </div>
                <div>
                    <span class="profile-label">Nível</span>
                    <p id="participant-bio-level"></p>
                </div>
            </div>

            <div class="user-details-bio">
                <span class="profile-label">Bio</span>
                <p id="participant-bio-text"></p>
            </div>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('participant-bio-modal');
        if (!modal) {
            return;
        }

        function fillText(id, value, fallback) {
            const el = document.getElementById(id);
            if (!el) {
                return;
            }
            const text = (value || '').trim();
            el.textContent = text !== '' ? text : fallback;
            el.classList.toggle('is-empty', text === '');
        }

        function openBio(trigger) {
            const nickname = trigger.dataset.nickname || trigger.dataset.username || 'Participante';
            const username = (trigger.dataset.username || '').trim();
            const photo = (trigger.dataset.photo || '').trim();
            const since = (trigger.dataset.memberSince || '').trim();
            const photoImg = document.getElementById('participant-bio-photo');
            const photoFallback = document.getElementById('participant-bio-photo-fallback');
            const memberSinceEl = document.getElementById('participant-bio-member-since');

            fillText('participant-bio-title', nickname, 'Participante');
            fillText('participant-bio-username', username !== '' ? '@' + username : '', '');
            fillText('participant-bio-name', trigger.dataset.realName, 'Não informado');
            fillText('participant-bio-level', trigger.dataset.level, 'Não informado');
            fillText(
                'participant-bio-text',
                trigger.dataset.bio,
                'Este usuário ainda não escreveu uma bio.'
            );

            if (memberSinceEl) {
                memberSinceEl.textContent = since !== '' ? 'Membro desde ' + since : '';
                memberSinceEl.hidden = since === '';
            }

            if (photoImg && photoFallback) {
                if (photo) {
                    photoImg.src = photo;
                    photoImg.hidden = false;
                    photoFallback.hidden = true;
                } else {
                    photoImg.removeAttribute('src');
                    photoImg.hidden = true;
                    photoFallback.hidden = false;
                }
            }

            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('user-details-open');
        }

        function closeBio() {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('user-details-open');
        }

        document.querySelectorAll('[data-open-bio]').forEach(function (button) {
            button.addEventListener('click', function () {
                openBio(button);
            });
        });

        modal.addEventListener('click', function (event) {
            if (event.target.closest('[data-close-bio]')) {
                closeBio();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                closeBio();
            }
        });

        document.querySelectorAll('[data-file-picker]').forEach(function (input) {
            const nameEl = input.closest('.file-picker')?.querySelector('[data-file-name]');
            if (!nameEl) {
                return;
            }

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                nameEl.textContent = file ? file.name : 'Nenhum arquivo selecionado';
            });
        });
    });
</script>
