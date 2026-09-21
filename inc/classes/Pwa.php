<?php

namespace Core2;

/**
 * Progressive Web App support.
 *
 * Отвечает за отдачу manifest.webmanifest, service-worker.js, офлайн страницы и
 * иконок, а также за внедрение соответствующих мета-тегов в HTML приложения.
 *
 * Настройки в conf.ini (секция приложения), все опциональны:
 *
 *   system.pwa.enabled          = true|false
 *   system.pwa.name             = Название приложения
 *   system.pwa.short_name       = Короткое название
 *   system.pwa.description      = Описание
 *   system.pwa.lang             = ru
 *   system.pwa.start_url        = /
 *   system.pwa.scope            = /
 *   system.pwa.display          = standalone
 *   system.pwa.orientation      = any
 *   system.pwa.theme_color      = #ffffff
 *   system.pwa.background_color = #ffffff
 *   system.pwa.icon             = path/to/icon.png
 */
class Pwa
{
    private const SERVICE_WORKER_VERSION = '1';

    private ?\Laminas\Config\Config $config;
    private ?\Laminas\Config\Config $core_config;
    private string $base;
    private string $icon_dir;

    /** @var array<string, mixed> */
    private array $settings = [];

    public function __construct() {
        $registry = Registry::getInstance();
        $config   = $registry->isRegistered('config') ? $registry->get('config') : null;
        $core_config   = $registry->isRegistered('core_config') ? $registry->get('core_config') : null;

        $this->config = $config instanceof \Laminas\Config\Config ? $config : null;
        $this->core_config = $core_config instanceof \Laminas\Config\Config ? $core_config : null;

        $doc_path   = defined('DOC_PATH') ? DOC_PATH : '/';
        $this->base = rtrim($doc_path, '/') . '/';

        $this->icon_dir = __DIR__ . '/../pwa/icons';

        $this->settings = $this->buildSettings();
    }


    /**
     * Отдаёт ответ для PWA маршрутов (manifest, service worker, offline, иконки).
     *
     * @param array<string, mixed> $route
     * @return string|null null если маршрут не относится к PWA
     */
    public function dispatch(array $route): ?string {
        if (empty($this->settings['enabled'])) {
            return null;
        }

        $module = isset($route['module']) ? strtolower((string) $route['module']) : '';
        $api    = isset($route['api'])    ? strtolower((string) $route['api'])    : '';
        $action = isset($route['action']) ? strtolower((string) $route['action']) : '';

        if (in_array($module, ['manifest.webmanifest', 'manifest.json'], true)) {
            return $this->manifestResponse();
        }

        if (in_array($module, ['service-worker.js', 'sw.js', 'service-worker'], true)) {
            return $this->serviceWorkerResponse();
        }

        if (in_array($module, ['offline.html', 'offline'], true)) {
            return $this->offlineResponse();
        }

        if ($api === 'pwa') {
            return $this->iconResponse($action);
        }

        return null;
    }


    /**
     * Внедряет PWA мета-теги и регистрацию service worker в готовый HTML.
     */
    public function inject(string $html): string {
        if (empty($this->settings['enabled']) || $html === '') {
            return $html;
        }

        if (stripos($html, '</head>') !== false && stripos($html, 'rel="manifest"') === false) {
            $html = (string) preg_replace('/<\/head>/i', $this->getHeadTags() . "\n</head>", $html, 1);
        }

        if (stripos($html, '</body>') !== false && stripos($html, 'serviceWorker.register') === false) {
            $html = (string) preg_replace('/<\/body>/i', $this->getBodyScript() . "\n</body>", $html, 1);
        }

        return $html;
    }


    /**
     * @return array<string, mixed>
     */
    public function getManifest(): array {
        $manifest = [
            'id'               => $this->base,
            'name'             => $this->settings['name'],
            'short_name'       => $this->settings['short_name'],
            'start_url'        => $this->settings['start_url'],
            'scope'            => $this->settings['scope'],
            'display'          => $this->settings['display'],
            'orientation'      => $this->settings['orientation'],
            'background_color' => $this->settings['background_color'],
            'theme_color'      => $this->settings['theme_color'],
            'icons'            => [
                [
                    'src'     => 'pwa/icon-192.png',
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => 'pwa/icon-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => 'pwa/icon-maskable-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        if (!empty($this->settings['description'])) {
            $manifest['description'] = $this->settings['description'];
        }

        if (!empty($this->settings['lang'])) {
            $manifest['lang'] = $this->settings['lang'];
        }

        return $manifest;
    }


    public function getManifestJson(): string {
        $json = json_encode(
            $this->getManifest(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );

        return $json === false ? '{}' : $json;
    }


    /**
     * HTML теги для секции <head>.
     */
    public function getHeadTags(): string {
        $name  = htmlspecialchars((string) $this->settings['name'], ENT_QUOTES, 'UTF-8');
        $short = htmlspecialchars((string) $this->settings['short_name'], ENT_QUOTES, 'UTF-8');
        $theme = htmlspecialchars((string) $this->settings['theme_color'], ENT_QUOTES, 'UTF-8');

        return implode("\n", [
            '<link rel="manifest" href="' . $this->base . 'manifest.webmanifest">',
            '<meta name="theme-color" content="' . $theme . '">',
            '<meta name="application-name" content="' . $name . '">',
            '<meta name="mobile-web-app-capable" content="yes">',
            '<meta name="apple-mobile-web-app-capable" content="yes">',
            '<meta name="apple-mobile-web-app-status-bar-style" content="default">',
            '<meta name="apple-mobile-web-app-title" content="' . $short . '">',
            '<link rel="apple-touch-icon" href="' . $this->base . 'pwa/apple-touch-icon.png">',
        ]);
    }


    /**
     * Скрипт регистрации service worker для конца <body>.
     */
    public function getBodyScript(): string {
        $sw     = json_encode($this->base . 'service-worker.js', JSON_UNESCAPED_SLASHES);
        $sw_alt = json_encode($this->base . 'service-worker', JSON_UNESCAPED_SLASHES);
        $scope  = json_encode((string) $this->settings['scope'], JSON_UNESCAPED_SLASHES);

        return '<script>(function(){' .
            'if(!("serviceWorker" in navigator)){return;}' .
            'window.addEventListener("load",function(){' .
            'var options={scope:' . $scope . '};' .
            'navigator.serviceWorker.register(' . $sw . ',options)' .
            '.catch(function(){return navigator.serviceWorker.register(' . $sw_alt . ',options);})' .
            '.catch(function(e){console.warn("PWA service worker registration failed",e);});' .
            '});' .
            '})();</script>';
    }


    public function getServiceWorker(): string {
        $version = self::SERVICE_WORKER_VERSION;
        $base    = $this->base;
        $name    = json_encode((string) $this->settings['short_name'], JSON_UNESCAPED_UNICODE);

        return <<<JS
/* Core2 PWA service worker (v{$version}) */
const CACHE_NAME = 'core2-pwa-v{$version}';
const OFFLINE_URL = '{$base}offline.html';
const PRECACHE_URLS = [
    '{$base}manifest.webmanifest',
    '{$base}offline.html',
    '{$base}pwa/icon-192.png',
    '{$base}pwa/icon-512.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => Promise.all(
                PRECACHE_URLS.map((url) => cache.add(new Request(url, { cache: 'reload' })).catch(() => null))
            ))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.indexOf('/api/') !== -1 || request.headers.get('X-Requested-With') === 'XMLHttpRequest') {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() =>
                caches.match(OFFLINE_URL).then((response) => response || caches.match(request))
            )
        );
        return;
    }

    if (['script', 'style', 'image', 'font'].indexOf(request.destination) !== -1) {
        event.respondWith(
            caches.open(CACHE_NAME).then((cache) =>
                cache.match(request).then((cached) => {
                    const network = fetch(request)
                        .then((response) => {
                            if (response && response.status === 200 && response.type === 'basic') {
                                cache.put(request, response.clone());
                            }
                            return response;
                        })
                        .catch(() => cached);

                    return cached || network;
                })
            )
        );
    }
});

self.addEventListener('push', (event) => {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    const sendNotification = (payload) => {
        const options = { body: payload.body, badge: '{$base}pwa/icon-192.png' };

        if (payload.badge) options.badge = payload.badge;
        if (payload.icon) options.icon = payload.icon;
        if (payload.image) options.image = payload.image;

        return self.registration.showNotification(payload.title, options);
    };

    if (event.data) {
        event.waitUntil(sendNotification(event.data.json()));
    }
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = event.notification.data && event.notification.data.url ? event.notification.data.url : '{$base}';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if (client.url === target && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(target);
            }
        })
    );
});
JS;
    }


    private function manifestResponse(): string {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');

        return $this->getManifestJson();
    }


    private function serviceWorkerResponse(): string {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Service-Worker-Allowed: ' . $this->settings['scope']);

        return $this->getServiceWorker();
    }


    private function offlineResponse(): string {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');

        $name = htmlspecialchars((string) $this->settings['name'], ENT_QUOTES, 'UTF-8');
        $bg   = htmlspecialchars((string) $this->settings['background_color'], ENT_QUOTES, 'UTF-8');
        $fg   = htmlspecialchars((string) $this->settings['theme_color'], ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8">' .
            '<meta name="viewport" content="width=device-width, initial-scale=1">' .
            '<title>' . $name . '</title>' .
            '<style>html,body{height:100%;margin:0}body{display:flex;align-items:center;justify-content:center;' .
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:' . $bg . ';color:#333}' .
            '.box{text-align:center;padding:24px}.box h1{margin:0 0 8px;font-size:20px}' .
            '.box p{margin:0 0 20px;color:#777}.box button{border:0;border-radius:8px;padding:10px 22px;font-size:15px;' .
            'cursor:pointer;background:' . $fg . ';color:#fff}</style></head>' .
            '<body><div class="box"><h1>' . $name . '</h1><p>Нет подключения к интернету</p>' .
            '<button onclick="location.reload()">Повторить</button></div></body></html>';
    }


    private function iconResponse(string $name): ?string {
        $name = basename($name);

        if (!in_array($name, ['icon-192.png', 'icon-512.png', 'icon-maskable-512.png', 'apple-touch-icon.png'], true)) {
            return null;
        }

        $path = $this->resolveIcon($name);

        if (!$path || !is_file($path)) {
            http_response_code(404);
            return '';
        }

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=604800');

        return (string) file_get_contents($path);
    }


    private function resolveIcon(string $name): ?string {
        $maskable = str_contains($name, 'maskable');
        $size     = preg_match('/(\d+)/', $name, $match) ? (int) $match[1] : 180;

        $generated = $this->generateIcon($size, $maskable);

        if ($generated) {
            return $generated;
        }

        $fallback = $this->icon_dir . '/' . $name;

        return is_file($fallback) ? $fallback : null;
    }


    /**
     * Генерирует иконку нужного размера из логотипа приложения (требует GD).
     */
    private function generateIcon(int $size, bool $maskable): ?string {
        if ($size < 1 || !function_exists('imagecreatetruecolor')) {
            return null;
        }

        $source = $this->getIconSource();

        if (!$source) {
            return null;
        }

        $cache_dir = $this->getCacheDir();

        if (!$cache_dir) {
            return null;
        }

        $cache_file = $cache_dir . '/' . ($maskable ? 'maskable-' : '') . $size . '-' .
            md5($source . '|' . filemtime($source) . '|' . $size . '|' . ($maskable ? '1' : '0')) . '.png';

        if (is_file($cache_file)) {
            return $cache_file;
        }

        $data = @file_get_contents($source);

        if ($data === false) {
            return null;
        }

        $image = @imagecreatefromstring($data);

        if (!$image) {
            return null;
        }

        $width  = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($size, $size);

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        if ($maskable) {
            [$r, $g, $b] = $this->hexToRgb((string) $this->settings['background_color']);
            $background  = imagecolorallocate($canvas, $r, $g, $b);
            imagefilledrectangle($canvas, 0, 0, $size, $size, $background);
            imagealphablending($canvas, true);

            $target = (int) round($size * 0.8);
            $offset = (int) round(($size - $target) / 2);

            imagecopyresampled($canvas, $image, $offset, $offset, 0, 0, $target, $target, $width, $height);
        } else {
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);
            imagealphablending($canvas, true);

            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $size, $size, $width, $height);
        }

        $saved = imagepng($canvas, $cache_file);

        imagedestroy($canvas);
        imagedestroy($image);

        return $saved && is_file($cache_file) ? $cache_file : null;
    }


    private function getIconSource(): ?string {
        $candidates = [];

        $icon = $this->cfgString('system.pwa.icon');

        if ($icon) {
            $candidates[] = $icon;
        }

        $logo = $this->cfgString('system.logo');

        if ($logo) {
            $candidates[] = $logo;
        }

        if (defined('THEME')) {
            $candidates[] = 'core2/html/' . THEME . '/img/logo.gif';
            $candidates[] = 'core2/html/' . THEME . '/img/favicon.png';
        }

        $candidates[] = 'favicon.png';

        foreach ($candidates as $candidate) {
            $path = $this->resolvePath($candidate);

            if ($path && is_file($path)) {
                return $path;
            }
        }

        return null;
    }


    private function resolvePath(string $path): ?string {
        if ($path === '' || preg_match('~^https?://~i', $path)) {
            return null;
        }

        if (is_file($path)) {
            return $path;
        }

        if (defined('DOC_ROOT')) {
            $absolute = rtrim(DOC_ROOT, '/') . '/' . ltrim($path, '/');

            if (is_file($absolute)) {
                return $absolute;
            }
        }

        return null;
    }


    private function getCacheDir(): ?string {
        $dir = rtrim(sys_get_temp_dir(), '/') . '/core2_pwa';

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        return is_writable($dir) ? $dir : null;
    }


    /**
     * @return array{0:int,1:int,2:int}
     */
    private function hexToRgb(string $hex): array {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return [255, 255, 255];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }


    /**
     * @return array<string, mixed>
     */
    private function buildSettings(): array {
        $enabled = true;
        $pwa     = $this->cfg('system.pwa');

        if ($pwa instanceof \Laminas\Config\Config && $pwa->get('enabled') !== null) {
            $enabled = $this->toBool($pwa->get('enabled'));
        }

        $name = $this->cfgString('system.pwa.name');

        if ($name === null || $name === '') {
            $name = $this->cfgString('system.name');
        }

        if ($name === null || $name === '') {
            $name = 'CORE2';
        }

        $short_name = $this->cfgString('system.pwa.short_name');

        if ($short_name === null || $short_name === '') {
            $short_name = $name;
        }

        $theme_color = $this->cfgString('system.pwa.theme_color');

        if ($theme_color === null || $theme_color === '') {
            $theme_color = $this->cfgString('system.theme.bg_color');
        }

        if ($theme_color === null || $theme_color === '') {
            $theme_color = '#ffffff';
        }

        $background_color = $this->cfgString('system.pwa.background_color');

        if ($background_color === null || $background_color === '') {
            $background_color = '#ffffff';
        }

        $start_url = $this->cfgString('system.pwa.start_url');

        if ($start_url === null || $start_url === '') {
            $start_url = $this->base;
        }

        $scope = $this->cfgString('system.pwa.scope');

        if ($scope === null || $scope === '') {
            $scope = $this->base;
        }

        return [
            'enabled'          => $enabled,
            'name'             => $name,
            'short_name'       => $short_name,
            'description'      => (string) $this->cfgString('system.pwa.description', ''),
            'lang'             => (string) $this->cfgString('system.pwa.lang', 'ru'),
            'start_url'        => $start_url,
            'scope'            => $scope,
            'display'          => (string) $this->cfgString('system.pwa.display', 'standalone'),
            'orientation'      => (string) $this->cfgString('system.pwa.orientation', 'any'),
            'theme_color'      => $theme_color,
            'background_color' => $background_color,
        ];
    }


    /**
     * Читает значение из конфига по пути вида "system.pwa.name".
     *
     * @return mixed
     */
    private function cfg(string $path) {
        if (!$this->core_config) {
            return null;
        }

        $node = $this->core_config;

        foreach (explode('.', $path) as $key) {
            if (!$node instanceof \Laminas\Config\Config) {
                return null;
            }

            $node = $node->get($key);

            if ($node === null) {
                return null;
            }
        }

        return $node;
    }


    private function cfgString(string $path, ?string $default = null): ?string {
        $value = $this->cfg($path);

        if (is_object($value)) {
            return $default;
        }

        if ($value === null || $value === false || $value === '') {
            return $default;
        }

        return (string) $value;
    }


    /**
     * @param mixed $value
     */
    private function toBool($value): bool {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on', 'y'], true);
    }
}
