<?php
/** @var \VEngine\System\View\ViewInterface $this */
?>
<section class="hero" id="top">
    <div class="hero-copy">
        <p class="eyebrow"><span class="status-dot"></span> PHP 8.2+ · модульная архитектура</p>
        <h1>Ваш сайт.<br><span>Ваши правила.</span></h1>
        <p class="hero-lead">
            V-engine помогает собирать сайты из понятных частей: ядро отдельно,
            код сайта отдельно, повторно используемые модули — там, где им место.
        </p>
        <div class="hero-actions">
            <a class="button button-primary" href="#create">Создать сайт <span aria-hidden="true">→</span></a>
            <a class="button button-quiet" href="#structure">Изучить структуру</a>
        </div>
        <div class="hero-note">
            <span class="note-icon" aria-hidden="true">✦</span>
            <span>Один экземпляр приложения — один активный сайт.<br>Выбор сайта задаётся через <code>APP_SITE</code>.</span>
        </div>
    </div>

    <div class="hero-visual" aria-label="Схема устройства проекта">
        <div class="visual-glow"></div>
        <div class="orbit orbit-one"></div>
        <div class="orbit orbit-two"></div>
        <div class="orbit orbit-three"></div>
        <div class="engine-core">
            <span class="core-mark">V</span>
            <span class="core-label">V-ENGINE</span>
            <span class="core-caption">одна основа</span>
        </div>
        <div class="orbit-tag tag-site"><span class="tag-dot violet"></span>ваш сайт</div>
        <div class="orbit-tag tag-modules"><span class="tag-dot cyan"></span>модули</div>
        <div class="orbit-tag tag-assets"><span class="tag-dot lime"></span>assets</div>
        <div class="visual-caption">собирайте своё · переиспользуйте общее</div>
    </div>
</section>

<section class="trust-strip" aria-label="Возможности движка">
    <div><span class="strip-number">01</span><span>Свои маршруты<br>и контроллеры</span></div>
    <div><span class="strip-number">02</span><span>Шаблоны и стили<br>в одном месте</span></div>
    <div><span class="strip-number">03</span><span>Модули без<br>смешения с сайтом</span></div>
    <div><span class="strip-number">04</span><span>Смена сайта<br>одной настройкой</span></div>
</section>

<section class="section intro-section" id="structure">
    <div class="section-heading">
        <p class="eyebrow">ПОНЯТНЫЕ ГРАНИЦЫ</p>
        <h2>Сразу видно,<br>где живёт сайт.</h2>
        <p>Откройте папку <code>sites/Corporate</code> — здесь находится всё, что формирует именно этот сайт: его страницы, адреса, стили и скрипты.</p>
    </div>
    <div class="file-card">
        <div class="file-card-top">
            <div class="window-dots"><i></i><i></i><i></i></div>
            <span>СТРУКТУРА ПРОЕКТА</span>
            <span class="file-card-badge">Corporate</span>
        </div>
        <div class="file-tree">
            <div class="tree-line tree-root"><span class="tree-icon folder"></span><strong>sites/Corporate/</strong><span class="tree-hint">текущий сайт</span></div>
            <div class="tree-line indent-1"><span class="tree-icon folder"></span><strong>App/</strong><span class="tree-hint">контроллеры, сервисы, middleware</span></div>
            <div class="tree-line indent-1"><span class="tree-icon folder"></span><strong>routes/</strong><span class="tree-hint">URL сайта</span></div>
            <div class="tree-line indent-1"><span class="tree-icon folder"></span><strong>views/</strong><span class="tree-hint">страницы и layout</span></div>
            <div class="tree-line indent-1"><span class="tree-icon folder"></span><strong>public/</strong><span class="tree-hint">публичные файлы сайта</span></div>
            <div class="tree-line indent-2"><span class="tree-icon folder accent"></span><strong>assets/css/</strong><span class="tree-hint">стили</span></div>
            <div class="tree-line indent-2"><span class="tree-icon folder accent"></span><strong>assets/js/</strong><span class="tree-hint">скрипты</span></div>
            <div class="tree-line indent-2"><span class="tree-icon folder accent"></span><strong>assets/images/</strong><span class="tree-hint">изображения</span></div>
            <div class="tree-divider"></div>
            <div class="tree-line tree-shared"><span class="tree-icon cube"></span><strong>modules/</strong><span class="tree-hint">общие возможности</span></div>
            <div class="tree-line tree-shared"><span class="tree-icon cube"></span><strong>src/</strong><span class="tree-hint">ядро фреймворка</span></div>
        </div>
        <div class="file-card-bottom"><span class="check-mark">✓</span> Код сайта не смешивается с ядром</div>
    </div>
</section>

<section class="section steps-section" id="create">
    <div class="section-heading centered">
        <p class="eyebrow">СТАРТ ЗА НЕСКОЛЬКО ШАГОВ</p>
        <h2>Новый сайт —<br><span class="text-gradient">новая папка.</span></h2>
        <p>Создайте отдельное пространство, не затрагивая текущий сайт и общие модули.</p>
    </div>
    <div class="steps-grid">
        <article class="step-card">
            <div class="step-number">01 <span> / 03</span></div>
            <h3>Создайте структуру</h3>
            <p>Например, для магазина назовите сайт <code>Store</code>.</p>
            <pre><code>mkdir -p sites/Store/{App/{Controllers,Services,Middleware},routes,views,public/assets/{css,js,images}}</code></pre>
        </article>
        <article class="step-card">
            <div class="step-number">02 <span> / 03</span></div>
            <h3>Выберите сайт</h3>
            <p>Укажите его имя в `.env`. Имя папки и значение настройки должны совпадать.</p>
            <pre><code>APP_SITE=Store
APP_ENV=dev</code></pre>
        </article>
        <article class="step-card">
            <div class="step-number">03 <span> / 03</span></div>
            <h3>Укажите document root</h3>
            <p>Веб-сервер должен смотреть только в публичную папку выбранного сайта.</p>
            <pre><code>php -S localhost:8000 \
  -t sites/Store/public</code></pre>
        </article>
    </div>
</section>

<section class="section placement-section" id="where-code-goes">
    <div class="section-heading">
        <p class="eyebrow">КУДА ДОБАВЛЯТЬ ФУНКЦИИ</p>
        <h2>Своё — к сайту.<br>Общее — в модуль.</h2>
        <p>Простое правило помогает не превратить проект в набор несвязанных папок.</p>
    </div>
    <div class="placement-grid">
        <article class="placement-card site-card">
            <div class="placement-icon">⌂</div>
            <div class="placement-label">ТОЛЬКО ДЛЯ ЭТОГО САЙТА</div>
            <h3><code>sites/Store/App/</code></h3>
            <p>Страницы каталога, корзина, оформление заказа, внутренние сервисы сайта.</p>
            <ul>
                <li><code>Controllers/</code> — обработчики страниц</li>
                <li><code>Services/</code> — логика предметной области</li>
                <li><code>Middleware/</code> — правила доступа сайта</li>
            </ul>
            <div class="namespace-chip">Sites\Store\App\...</div>
        </article>
        <article class="placement-card module-card">
            <div class="placement-icon">◇</div>
            <div class="placement-label">ПЕРЕИСПОЛЬЗУЕТСЯ В РАЗНЫХ САЙТАХ</div>
            <h3><code>modules/</code></h3>
            <p>Самостоятельная возможность, которую можно подключать к разным сайтам.</p>
            <ul>
                <li><code>modules/Blog/</code> — блог</li>
                <li><code>modules/Admin/</code> — панель управления</li>
                <li>У модуля могут быть свои routes, views и services</li>
            </ul>
            <div class="namespace-chip">Modules\Blog\...</div>
        </article>
    </div>
    <div class="decision-note">
        <span class="decision-mark">?</span>
        <p><strong>Не уверены, куда положить код?</strong> Если он нужен только одному сайту — начинайте с `sites/<Имя>/App/`. Переносите его в `modules/`, когда появится реальный второй потребитель.</p>
    </div>
</section>

<section class="section example-section" id="first-page">
    <div class="example-copy">
        <p class="eyebrow">ПЕРВАЯ СТРАНИЦА</p>
        <h2>Маршрут.<br>Контроллер.<br><span class="text-gradient">Шаблон.</span></h2>
        <p>Каждый слой делает свою работу. Маршрут связывает URL с контроллером, контроллер готовит данные, а шаблон отвечает за HTML.</p>
        <a class="text-link" href="#next-steps">Посмотреть следующие шаги <span aria-hidden="true">↘</span></a>
    </div>
    <div class="code-window">
        <div class="code-tabs">
            <span class="code-tab active"><i></i> routes/web.php</span>
            <span class="code-tab"><i></i> App/Controllers/HomeController.php</span>
        </div>
        <div class="code-body">
            <div class="code-file-label">sites/Store/routes/web.php</div>
            <pre><code><span class="code-purple">use</span> Sites\Store\App\Controllers\HomeController;
<span class="code-purple">use</span> VEngine\System\Router\RouterInterface;

<span class="code-purple">return</span> <span class="code-blue">static function</span> (RouterInterface $router): <span class="code-blue">void</span> {
    $router-&gt;get(<span class="code-green">'/'</span>,
        [HomeController::<span class="code-yellow">class</span>, <span class="code-green">'index'</span>]
    )-&gt;name(<span class="code-green">'home'</span>);
};</code></pre>
            <div class="code-file-label second-file">sites/Store/views/home/index.php</div>
            <pre class="small-code"><code>&lt;h1&gt;Добро пожаловать в мой магазин&lt;/h1&gt;
&lt;p&gt;Страница использует layout и стили сайта.&lt;/p&gt;</code></pre>
        </div>
    </div>
</section>

<section class="section next-section" id="next-steps">
    <div class="next-panel">
        <div class="next-orb"></div>
        <div class="next-content">
            <p class="eyebrow">КОГДА САЙТ РАСТЁТ</p>
            <h2>Расширяйте сайт,<br>не ломая основу.</h2>
            <p>Добавляйте страницы и сервисы в его `App/`, храните стили в `public/assets/`, а повторно используемые функции оформляйте модулями.</p>
            <div class="next-links">
                <a class="button button-primary" href="https://github.com/Vampqwe/v-engine" target="_blank" rel="noopener noreferrer">Открыть проект на GitHub <span aria-hidden="true">↗</span></a>
                <a class="button button-quiet" href="#top">Наверх ↑</a>
            </div>
        </div>
        <div class="next-checklist">
            <div><span>01</span><strong>Сайт</strong><code>sites/Store/</code></div>
            <div><span>02</span><strong>Функции</strong><code>sites/Store/App/</code></div>
            <div><span>03</span><strong>Общие модули</strong><code>modules/</code></div>
            <div><span>04</span><strong>Публичные файлы</strong><code>sites/Store/public/</code></div>
        </div>
    </div>
</section>
