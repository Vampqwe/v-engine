<?php
/** @var \VEngine\System\View\ViewInterface $this */
/** @var string $content */
/** @var string|null $title */
/** @var array<string, mixed>|null $adminUser */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->escape($title ?? 'Admin | V-engine') ?></title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        :root {
            --bg-canvas: #0d1117;
            --bg-card: #161b22;
            --border: #30363d;
            --text-main: #e6edf3;
            --text-muted: #8b949e;
            --accent: #58a6ff;
            --danger: #f85149;
            --success: #3fb950;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg-canvas);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .brand { font-weight: 700; color: var(--text-main); text-decoration: none; font-size: 16px; }
        .brand span { color: var(--accent); }
        .nav-actions { display: flex; align-items: center; gap: 16px; font-size: 14px; }
        .nav-actions a { color: var(--text-muted); text-decoration: none; }
        .nav-actions a:hover { color: var(--text-main); }
        .container { max-width: 1100px; width: 100%; margin: 32px auto; padding: 0 20px; flex: 1; }
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid transparent;
        }
        .alert-error { background: rgba(248, 81, 73, 0.12); border-color: rgba(248, 81, 73, 0.4); color: var(--danger); }
        .alert-success { background: rgba(63, 185, 80, 0.12); border-color: rgba(63, 185, 80, 0.4); color: var(--success); }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: #21262d;
            color: var(--text-main);
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover { background: #30363d; }
        .btn-primary { background: #238636; border-color: rgba(240, 246, 252, 0.1); color: #fff; }
        .btn-primary:hover { background: #2ea043; }
    </style>
</head>
<body class="admin-body">
    <header class="topbar">
        <a href="<?= $this->route('admin.dashboard') ?>" class="brand">V-engine <span>Admin</span></a>
        <div class="nav-actions">
            <a href="<?= $this->route('home') ?>">На сайт</a>
            <?php if (!empty($adminUser)): ?>
                <span style="color: var(--text-muted);"><?= $this->escape($adminUser['email'] ?? '') ?></span>
                <form method="POST" action="<?= $this->route('admin.logout') ?>" style="display:inline;">
                    <?= $this->csrfField() ?>
                    <button type="submit" class="btn">Выйти</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <main class="container">
        <?php if ($error = $this->flash('error')): ?>
            <div class="alert alert-error"><?= $this->escape($error) ?></div>
        <?php endif; ?>

        <?php if ($success = $this->flash('success')): ?>
            <div class="alert alert-success"><?= $this->escape($success) ?></div>
        <?php endif; ?>

        <?= $content ?>
    </main>
</body>
</html>
