<?php
/** @var \VEngine\System\View\ViewInterface $this */
/** @var string $username */
?>
<h1>Добро пожаловать, <?= $this->escape($username) ?>!</h1>

<form method="POST" action="<?= $this->route('home') ?>">
    <?= $this->csrfField() ?>
    <input type="text" name="message" placeholder="Введите сообщение">
    <button type="submit">Отправить</button>
</form>
