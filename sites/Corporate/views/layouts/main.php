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
    <meta name="theme-color" content="#0b0c12">
    <meta name="description" content="V-engine — модульный PHP-движок. Узнайте, как создать сайт, где хранить его код и как подключать общие модули.">
    <title><?= $this->escape($title ?? 'V-engine — создавайте сайты по-своему') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="site-page">
    <header class="site-header">
        <a class="brand" href="<?= $this->route('home') ?>" aria-label="V-engine, на главную">
            <span class="brand-mark">V</span>
            <span>v-engine<span class="brand-period">.</span></span>
        </a>
        <nav class="site-nav" aria-label="Основная навигация">
            <a href="#structure">Структура</a>
            <a href="#create">Новый сайт</a>
            <a href="#where-code-goes">Модули</a>
        </nav>
        <a class="header-link" href="https://github.com/Vampqwe/v-engine" target="_blank" rel="noopener noreferrer">GitHub <span aria-hidden="true">↗</span></a>
    </header>

    <?php if ($notice = $this->flash('notice')): ?>
        <div class="alert site-alert"><?= $this->escape($notice) ?></div>
    <?php endif; ?>

    <main class="site-main">
        <?= $content ?>
    </main>

    <footer class="site-footer">
        <a class="brand footer-brand" href="#top"><span class="brand-mark">V</span><span>v-engine<span class="brand-period">.</span></span></a>
        <p>Чистая структура. Свобода создавать.</p>
        <span class="footer-version">PHP 8.2+ · OPEN SOURCE</span>
    </footer>
</body>
</html>
