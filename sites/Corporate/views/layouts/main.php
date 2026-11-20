<?php
/** @var \VEngine\System\View\ViewInterface $this */
/** @var string $content */
/** @var string|null $title */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->escape($title ?? 'V-engine') ?></title>
</head>
<body>
    <header>
        <nav>
            <a href="<?= $this->route('home') ?>">Главная</a>
            <a href="<?= $this->route('api.health') ?>">API Health</a>
        </nav>
    </header>

    <?php if ($notice = $this->flash('notice')): ?>
        <div class="alert"><?= $this->escape($notice) ?></div>
    <?php endif; ?>

    <main>
        <?= $content ?>
    </main>
</body>
</html>
