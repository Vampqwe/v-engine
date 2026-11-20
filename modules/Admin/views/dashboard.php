<?php
/** @var \VEngine\System\View\ViewInterface $this */
/** @var array<string, mixed> $adminUser */
/** @var array<string, mixed> $stats */
$application = $stats['application'];
$runtime = $stats['runtime'];
$structure = $stats['structure'];
$storage = $stats['storage'];
$database = $stats['database'];
$cache = $stats['cache'];
$session = $stats['session'];
$statusLabel = static fn(string $status): string => match ($status) {
    'connected', 'configured', 'writable' => 'Работает',
    'not_used' => 'Не используется',
    'unavailable', 'readonly' => 'Недоступно',
    default => 'Проверить',
};
$statusClass = static fn(string $status): string => match ($status) {
    'connected', 'configured', 'writable' => 'status-ok',
    'not_used' => 'status-muted',
    default => 'status-warning',
};
?>
<section class="admin-welcome">
    <div>
        <p class="admin-eyebrow"><span class="live-dot"></span> ОБЗОР СИСТЕМЫ · <?= $this->escape($application['site']) ?></p>
        <h1>Добро пожаловать, <?= $this->escape($adminUser['name'] ?? 'Администратор') ?></h1>
        <p>Состояние движка и инфраструктуры на <?= $this->escape($runtime['server_time']) ?>.</p>
    </div>
    <div class="welcome-meta">
        <span class="env-badge env-<?= $this->escape(strtolower($application['environment'])) ?>">
            <?= $this->escape(strtoupper($application['environment'])) ?>
        </span>
        <span class="welcome-account"><?= $this->escape($adminUser['email'] ?? '') ?></span>
    </div>
</section>

<section class="admin-metrics" aria-label="Основные показатели">
    <article class="metric-card metric-primary">
        <div class="metric-top"><span>АКТИВНЫЙ САЙТ</span><span class="metric-icon">⌂</span></div>
        <strong><?= $this->escape($application['site']) ?></strong>
        <small><?= (int) $application['site_count'] ?> сайт(а) в проекте</small>
    </article>
    <article class="metric-card">
        <div class="metric-top"><span>МОДУЛИ</span><span class="metric-icon icon-cyan">◇</span></div>
        <strong><?= (int) $structure['module_count'] ?></strong>
        <small>подключаемых модулей</small>
    </article>
    <article class="metric-card">
        <div class="metric-top"><span>КОНФИГУРАЦИИ МАРШРУТОВ</span><span class="metric-icon icon-lime">↗</span></div>
        <strong><?= (int) $structure['route_files'] ?></strong>
        <small>файла маршрутов сайта</small>
    </article>
    <article class="metric-card">
        <div class="metric-top"><span>АДМИНИСТРАТОРЫ</span><span class="metric-icon icon-orange">◎</span></div>
        <strong><?= $database['admin_users'] === null ? '—' : (int) $database['admin_users'] ?></strong>
        <small><?= $database['admin_users'] === null ? 'таблица пока недоступна' : 'учётных записей' ?></small>
    </article>
</section>

<section class="admin-content-grid">
    <article class="admin-panel system-panel">
        <div class="panel-heading">
            <div>
                <p class="admin-eyebrow">СРЕДА ВЫПОЛНЕНИЯ</p>
                <h2>Система</h2>
            </div>
            <span class="panel-icon">⌘</span>
        </div>
        <div class="detail-list">
            <div class="detail-row"><span>Версия PHP</span><strong><?= $this->escape($runtime['php_version']) ?></strong></div>
            <div class="detail-row"><span>Интерфейс запуска</span><strong><?= $this->escape($runtime['sapi']) ?></strong></div>
            <div class="detail-row"><span>Использовано памяти</span><strong><?= $this->escape($runtime['memory_used']) ?></strong></div>
            <div class="detail-row"><span>Пиковое использование</span><strong><?= $this->escape($runtime['memory_peak']) ?></strong></div>
            <div class="detail-row"><span>Лимит памяти PHP</span><strong><?= $this->escape($runtime['memory_limit']) ?></strong></div>
        </div>
        <div class="extension-list">
            <p class="list-caption">РАСШИРЕНИЯ PHP</p>
            <?php foreach ($runtime['php_extensions'] as $extension => $loaded): ?>
                <div class="extension-item">
                    <span class="extension-name"><?= $this->escape($extension) ?></span>
                    <span class="state-pill <?= $loaded ? 'status-ok' : 'status-warning' ?>">
                        <i></i><?= $loaded ? 'активно' : 'не установлено' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="admin-panel health-panel">
        <div class="panel-heading">
            <div>
                <p class="admin-eyebrow">ПОДКЛЮЧЕНИЯ И ХРАНИЛИЩА</p>
                <h2>Инфраструктура</h2>
            </div>
            <span class="panel-icon panel-icon-green">⌁</span>
        </div>
        <div class="health-list">
            <?php
            $databaseStatus = (string) $database['status'];
$redisStatus = (string) $cache['redis_status'];
$fileStatus = $cache['file_status'] ? 'writable' : 'readonly';
$healthItems = [
    ['name' => 'База данных MySQL', 'detail' => $database['version'] ?? 'Нет соединения', 'status' => $databaseStatus],
    ['name' => 'Redis', 'detail' => $redisStatus === 'not_used' ? 'Не выбран текущими драйверами' : ($redisStatus === 'connected' ? 'Соединение установлено' : 'Соединение недоступно'), 'status' => $redisStatus],
    ['name' => 'Файловый кэш сайта', 'detail' => $cache['driver'] === 'file' ? 'Основной драйвер' : ($cache['failover'] ? 'Резервное хранилище' : 'Не используется'), 'status' => $cache['driver'] === 'file' || $cache['failover'] ? $fileStatus : 'not_used'],
    ['name' => 'Сессии', 'detail' => 'Драйвер: ' . $session['driver'], 'status' => (string) $session['status']],
];
?>
            <?php foreach ($healthItems as $item): ?>
                <div class="health-item">
                    <span class="health-indicator <?= $this->escape($statusClass($item['status'])) ?>"></span>
                    <div class="health-copy">
                        <strong><?= $this->escape($item['name']) ?></strong>
                        <small><?= $this->escape($item['detail']) ?></small>
                    </div>
                    <span class="health-state <?= $this->escape($statusClass($item['status'])) ?>">
                        <?= $this->escape($statusLabel($item['status'])) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="admin-panel storage-panel">
        <div class="panel-heading">
            <div>
                <p class="admin-eyebrow">ДИСКОВОЕ ПРОСТРАНСТВО</p>
                <h2>Хранилище</h2>
            </div>
            <span class="panel-icon panel-icon-blue">▤</span>
        </div>
        <?php if ($storage['available']): ?>
            <div class="storage-numbers">
                <div><strong><?= $this->escape($storage['free']) ?></strong><span>свободно</span></div>
                <div><strong><?= $this->escape($storage['total']) ?></strong><span>всего на диске</span></div>
            </div>
            <div class="storage-track" role="progressbar" aria-label="Использование диска" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $storage['used_percent'] ?>">
                <span style="width: <?= (int) $storage['used_percent'] ?>%"></span>
            </div>
            <div class="storage-foot"><span>Использовано</span><strong><?= (int) $storage['used_percent'] ?>%</strong></div>
        <?php else: ?>
            <p class="unavailable-note">Не удалось получить сведения о дисковом пространстве.</p>
        <?php endif; ?>
        <div class="storage-path"><span>Путь</span><code>storage/</code></div>
    </article>

    <article class="admin-panel modules-panel">
        <div class="panel-heading">
            <div>
                <p class="admin-eyebrow">ВОЗМОЖНОСТИ ДВИЖКА</p>
                <h2>Подключённые модули</h2>
            </div>
            <span class="panel-icon panel-icon-purple">◈</span>
        </div>
        <?php if ($structure['modules'] !== []): ?>
            <div class="module-chips">
                <?php foreach ($structure['modules'] as $module): ?>
                    <span class="module-chip"><i></i><?= $this->escape($module) ?></span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="unavailable-note">В проекте пока нет модулей с файлами регистрации маршрутов.</p>
        <?php endif; ?>
        <div class="module-footnote">Модули находятся в <code>modules/</code> и могут подключать собственные маршруты, контроллеры и представления.</div>
    </article>
</section>

<footer class="dashboard-footer">
    <span><i class="footer-pulse"></i> Панель обновляет показатели при загрузке страницы</span>
    <span>V-engine · <?= $this->escape($application['site']) ?> · <?= $this->escape($runtime['server_time']) ?></span>
</footer>
