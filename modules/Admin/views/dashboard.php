<?php
/** @var \VEngine\System\View\ViewInterface $this */
/** @var array<string, mixed> $adminUser */
/** @var array<string, string> $stats */
?>
<h1 style="font-size: 24px; margin-bottom: 8px;">
    Добро пожаловать, <?= $this->escape($adminUser['name'] ?? 'Администратор') ?>!
</h1>
<p style="color: #8b949e; margin-bottom: 24px; font-size: 14px;">
    Вы вошли под учетной записью <strong><?= $this->escape($adminUser['email'] ?? '') ?></strong>.
</p>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
    <div style="background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 20px;">
        <div style="font-size: 12px; color: #8b949e; text-transform: uppercase;">Окружение (APP_ENV)</div>
        <div style="font-size: 22px; font-weight: 600; margin-top: 6px; color: #58a6ff;">
            <?= $this->escape($stats['app_env'] ?? 'unknown') ?>
        </div>
    </div>

    <div style="background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 20px;">
        <div style="font-size: 12px; color: #8b949e; text-transform: uppercase;">Версия PHP</div>
        <div style="font-size: 22px; font-weight: 600; margin-top: 6px;">
            <?= $this->escape($stats['php_version'] ?? '') ?>
        </div>
    </div>

    <div style="background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 20px;">
        <div style="font-size: 12px; color: #8b949e; text-transform: uppercase;">Время сервера</div>
        <div style="font-size: 22px; font-weight: 600; margin-top: 6px;">
            <?= $this->escape($stats['server_time'] ?? '') ?>
        </div>
    </div>
</div>
