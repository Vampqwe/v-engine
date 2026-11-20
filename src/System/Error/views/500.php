<?php
/** @var Throwable $exception */
/** @var array $codeSnippet */
/** @var array $trace */
/** @var ?Throwable $previous */
/** @var int $status */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $status ?> | <?= htmlspecialchars($exception::class) ?></title>
    <style>
        :root {
            --bg-canvas: #0d1117;
            --bg-subtle: #161b22;
            --bg-inset: #010409;
            --border-default: #30363d;
            --text-primary: #e6edf3;
            --text-secondary: #8b949e;
            --accent-danger: #f85149;
            --accent-danger-bg: rgba(248, 81, 73, 0.15);
            --accent-blue: #58a6ff;
            --accent-purple: #d2a8ff;
            --font-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
            --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-canvas);
            color: var(--text-primary);
            font-family: var(--font-sans);
            line-height: 1.5;
            padding: 24px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header-card {
            background: var(--bg-subtle);
            border: 1px solid var(--border-default);
            border-left: 4px solid var(--accent-danger);
            border-radius: 6px;
            padding: 20px 24px;
            margin-bottom: 24px;
        }
        .badge-500 {
            display: inline-block;
            background: var(--accent-danger-bg);
            color: var(--accent-danger);
            border: 1px solid rgba(248, 81, 73, 0.4);
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            margin-bottom: 10px;
        }
        .exception-class {
            font-family: var(--font-mono);
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }
        .exception-message {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-primary);
            word-break: break-word;
        }
        .file-location {
            margin-top: 12px;
            font-family: var(--font-mono);
            font-size: 13px;
            color: var(--accent-blue);
        }
        .section {
            background: var(--bg-subtle);
            border: 1px solid var(--border-default);
            border-radius: 6px;
            margin-bottom: 24px;
            overflow: hidden;
        }
        .section-title {
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 600;
            border-bottom: 1px solid var(--border-default);
            background: var(--bg-inset);
            color: var(--text-primary);
        }
        .code-block {
            font-family: var(--font-mono);
            font-size: 13px;
            overflow-x: auto;
            padding: 8px 0;
        }
        .code-line {
            display: flex;
            padding: 2px 16px;
            white-space: pre;
        }
        .code-line.error-line {
            background: var(--accent-danger-bg);
            border-left: 3px solid var(--accent-danger);
        }
        .line-num {
            width: 50px;
            color: var(--text-secondary);
            user-select: none;
            text-align: right;
            padding-right: 16px;
        }
        .error-line .line-num { color: var(--accent-danger); font-weight: bold; }
        .trace-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-default);
            font-family: var(--font-mono);
            font-size: 13px;
        }
        .trace-item:last-child { border-bottom: none; }
        .trace-call { color: var(--accent-purple); font-weight: 600; }
        .trace-file { color: var(--text-secondary); font-size: 12px; margin-top: 4px; }
        .env-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            font-family: var(--font-mono);
            font-size: 13px;
        }
        .env-key, .env-val {
            padding: 8px 16px;
            border-bottom: 1px solid var(--border-default);
        }
        .env-key { color: var(--text-secondary); background: rgba(1,4,9,0.4); }
    </style>
</head>
<body>
<div class="container">
    <div class="header-card">
        <span class="badge-500">HTTP <?= $status ?> • UNHANDLED EXCEPTION</span>
        <div class="exception-class"><?= htmlspecialchars($exception::class) ?> (Code: <?= (int) $exception->getCode() ?>)</div>
        <div class="exception-message"><?= htmlspecialchars($exception->getMessage()) ?></div>
        <div class="file-location">
            📄 <?= htmlspecialchars($exception->getFile()) ?> : <strong><?= $exception->getLine() ?></strong>
        </div>
    </div>

    <?php if (!empty($codeSnippet)): ?>
    <div class="section">
        <div class="section-title">Фрагмент исходного кода</div>
        <div class="code-block">
            <?php foreach ($codeSnippet as $num => $line): ?>
                <div class="code-line <?= $line['is_error'] ? 'error-line' : '' ?>">
                    <span class="line-num"><?= $num ?></span>
                    <span><?= htmlspecialchars($line['code']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="section">
        <div class="section-title">Стек вызовов (Stack Trace)</div>
        <?php foreach ($trace as $idx => $frame): ?>
            <div class="trace-item">
                <div class="trace-call">
                    #<?= $idx ?> 
                    <?= htmlspecialchars(($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? 'closure')) ?>()
                </div>
                <div class="trace-file">
                    <?= htmlspecialchars($frame['file'] ?? '[internal function]') ?>
                    <?php if (isset($frame['line'])): ?>
                        : <strong><?= $frame['line'] ?></strong>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($previous !== null): ?>
    <div class="section">
        <div class="section-title">Предыдущее исключение (Previous Exception)</div>
        <div class="trace-item">
            <div class="trace-call"><?= htmlspecialchars($previous::class) ?>: <?= htmlspecialchars($previous->getMessage()) ?></div>
            <div class="trace-file"><?= htmlspecialchars($previous->getFile()) ?> : <?= $previous->getLine() ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="section">
        <div class="section-title">Контекст запроса</div>
        <div class="env-grid">
            <div class="env-key">METHOD / URI</div>
            <div class="env-val"><?= htmlspecialchars(($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' ' . ($_SERVER['REQUEST_URI'] ?? '/')) ?></div>
            <div class="env-key">PHP VERSION</div>
            <div class="env-val"><?= PHP_VERSION ?></div>
        </div>
    </div>
</div>
</body>
</html>
