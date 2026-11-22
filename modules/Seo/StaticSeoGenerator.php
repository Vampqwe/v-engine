<?php

declare(strict_types=1);

namespace Modules\Seo;

use Modules\Seo\Exception\SeoException;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\Response;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Router\StaticSiteContext;

final class StaticSeoGenerator
{
    public function generate(StaticSiteContext $context): void
    {
        $baseUrl = $this->normalizeBaseUrl($context->baseUrl);
        foreach (['/sitemap.xml', '/robots.txt'] as $reservedPath) {
            if (isset($context->routes[$reservedPath]) || isset($context->aliases[$reservedPath])) {
                throw new SeoException("Static route is reserved for SEO output: {$reservedPath}");
            }
        }

        $sitemap = $this->buildSitemap($baseUrl, array_keys($context->routes));
        $robots = "User-agent: *\nAllow: /\nSitemap: {$baseUrl}/sitemap.xml\n";

        $this->writeAtomically($context->publicPath, 'sitemap.xml', $sitemap);
        $this->writeAtomically($context->publicPath, 'robots.txt', $robots);

        $this->registerResponseRoutes($context, $sitemap, $robots);
    }

    /**
     * @param list<string> $routes
     */
    private function buildSitemap(string $baseUrl, array $routes): string
    {
        sort($routes, SORT_STRING);
        $urls = '';
        foreach ($routes as $route) {
            $location = htmlspecialchars($baseUrl . $route, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $urls .= "  <url>\n    <loc>{$location}</loc>\n  </url>\n";
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            . $urls
            . "</urlset>\n";
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $parts = parse_url($baseUrl);
        if (
            $parts === false
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && !in_array($parts['path'], ['', '/'], true))
            || filter_var($baseUrl, FILTER_VALIDATE_URL) === false
        ) {
            throw new SeoException('APP_URL должен быть абсолютным HTTP(S) URL сайта без пути, query или fragment');
        }

        return rtrim($baseUrl, '/');
    }

    private function writeAtomically(string $publicPath, string $filename, string $content): void
    {
        if (!is_dir($publicPath) || !is_writable($publicPath)) {
            throw new SeoException("Каталог public должен существовать и быть доступен для записи: {$publicPath}");
        }

        $target = $publicPath . DIRECTORY_SEPARATOR . $filename;
        if (is_link($target)) {
            throw new SeoException("Сгенерированный SEO-файл не может быть symlink: {$target}");
        }
        if (is_file($target) && is_readable($target) && file_get_contents($target) === $content) {
            return;
        }

        $temporary = $target . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($temporary, $content, LOCK_EX) === false) {
            @unlink($temporary);
            throw new SeoException("Не удалось записать SEO-файл: {$target}");
        }
        if (!chmod($temporary, 0o644) || !rename($temporary, $target)) {
            @unlink($temporary);
            throw new SeoException("Не удалось атомарно заменить SEO-файл: {$target}");
        }
    }

    private function registerResponseRoutes(StaticSiteContext $context, string $sitemap, string $robots): void
    {
        foreach ([
            '/sitemap.xml' => ['content' => $sitemap, 'type' => 'application/xml; charset=UTF-8'],
            '/robots.txt'  => ['content' => $robots, 'type' => 'text/plain; charset=UTF-8'],
        ] as $path => $file) {
            $handler = static function (RequestInterface $request) use ($file): ResponseInterface {
                $content = $file['content'];
                return new Response(
                    $request->method() === 'HEAD' ? '' : $content,
                    200,
                    [
                        'Content-Type'   => $file['type'],
                        'Content-Length' => (string) strlen($content),
                    ],
                );
            };
            $context->router->add('GET', $path, $handler);
            $context->router->add('HEAD', $path, $handler);
        }
    }
}
