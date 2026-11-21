<?php
/** @var \VEngine\System\View\ViewInterface $this */
/** @var array<string, mixed>|null $result */
/** @var array<int, array{name: string, description: string}> $commands */
?>
<section class="admin-welcome">
    <div>
        <p class="admin-eyebrow">АДМИНИСТРИРОВАНИЕ</p>
        <h1>CLI-команды</h1>
        <p>Доступны только зарегистрированные безопасные операции. Shell-команды из браузера не выполняются.</p>
    </div>
</section>

<?php if ($result !== null): ?>
    <section class="admin-panel" aria-live="polite">
        <div class="panel-heading">
            <div>
                <p class="admin-eyebrow">РЕЗУЛЬТАТ</p>
                <h2><?= $this->escape($result['command'] ?? '') ?></h2>
            </div>
            <span class="state-pill <?= (int) ($result['exitCode'] ?? 1) === 0 ? 'status-ok' : 'status-warning' ?>">
                <?= (int) ($result['exitCode'] ?? 1) === 0 ? 'Завершено' : 'Ошибка' ?>
            </span>
        </div>
        <pre class="cli-output"><?= $this->escape($result['output'] ?? '') ?></pre>
    </section>
<?php endif; ?>

<section class="admin-panel">
    <div class="panel-heading">
        <div>
            <p class="admin-eyebrow">РАЗРЕШЁННЫЙ СПИСОК</p>
            <h2>Команды</h2>
        </div>
    </div>
    <div class="detail-list">
        <?php foreach ($commands as $command): ?>
            <div class="detail-row cli-command">
                <div>
                    <strong><code><?= $this->escape($command['name']) ?></code></strong>
                    <p><?= $this->escape($command['description']) ?></p>
                </div>
                <form method="POST" action="<?= $this->route('admin.cli.execute') ?>">
                    <?= $this->csrfField() ?>
                    <input type="hidden" name="command" value="<?= $this->escape($command['name']) ?>">
                    <button class="btn btn-primary" type="submit">Выполнить</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<style>
    .cli-output {
        overflow-x: auto;
        margin-top: 16px;
        padding: 16px;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: #090c10;
        color: var(--text-main);
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
    .cli-command { gap: 20px; }
    .cli-command p { color: var(--text-muted); margin-top: 4px; }
</style>
