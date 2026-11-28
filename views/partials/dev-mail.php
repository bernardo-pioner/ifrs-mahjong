<?php
/** @var list<array{to: string, subject: string, text: string}> $devMails */
if (empty($devMails)) {
    return;
}
?>
<div class="mail-preview">
    <p>O envio real ainda não está configurado. Esta é a mensagem que sairia por e-mail:</p>
    <?php foreach ($devMails as $mail): ?>
        <p><strong><?= e($mail['subject']) ?></strong> para <?= e($mail['to']) ?></p>
        <pre><?= preg_replace(
            '~(https?://[^\s<]+)~',
            '<a href="$1">$1</a>',
            e($mail['text'])
        ) ?></pre>
    <?php endforeach; ?>
</div>
