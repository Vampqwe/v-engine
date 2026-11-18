<?php
/** @var \VEngine\System\View\ViewInterface $this */
$oldEmail = (string) $this->flash('old_email', '');
?>
<div style="max-width: 400px; margin: 40px auto; background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 28px;">
    <h1 style="font-size: 20px; margin-bottom: 20px; text-align: center;">Вход в панель управления</h1>

    <form method="POST" action="<?= $this->route('admin.login.post') ?>">
        <?= $this->csrfField() ?>

        <div style="margin-bottom: 16px;">
            <label for="email" style="display: block; font-size: 13px; color: #8b949e; margin-bottom: 6px;">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= $this->escape($oldEmail) ?>"
                required
                autofocus
                style="width: 100%; padding: 9px 12px; border-radius: 6px; border: 1px solid #30363d; background: #0d1117; color: #e6edf3; font-size: 14px;"
            >
        </div>

        <div style="margin-bottom: 22px;">
            <label for="password" style="display: block; font-size: 13px; color: #8b949e; margin-bottom: 6px;">Пароль</label>
            <input
                type="password"
                id="password"
                name="password"
                required
                style="width: 100%; padding: 9px 12px; border-radius: 6px; border: 1px solid #30363d; background: #0d1117; color: #e6edf3; font-size: 14px;"
            >
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; font-weight: 600;">
            Войти
        </button>
    </form>
</div>
