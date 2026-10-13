<?php

namespace Sitetus\LaravelCollector\Http;

use Illuminate\Http\Request;
use Sitetus\Collector\Config;
use Sitetus\Collector\Envelope;
use Sitetus\Collector\Health;
use Sitetus\Collector\Inventory;
use Sitetus\Collector\Level2;
use Sitetus\Collector\Modules\ModuleRunner;
use Sitetus\Collector\NonceStore;
use Sitetus\Collector\RequestVerifier;
use Sitetus\LaravelCollector\CustomChecks;
use Symfony\Component\HttpFoundation\Response;

/**
 * The wrapper's pull endpoint: the same verification order, the same
 * envelope as the single file (the panel tells wrappers apart
 * only by collector.name), plus the custom_checks section. Level 2
 * needs no bootstrap: the application is already running.
 */
final class CollectorController
{
    public function __invoke(Request $request): Response
    {
        $config = $this->config();

        $verifier = new RequestVerifier($config, new NonceStore($config->noncePath()));

        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower($name)] = (string) ($values[0] ?? '');
        }

        $startedAt = microtime(true);

        // The raw URI path (trailing slash included): the panel signs exactly
        // the path it requests, and Request::path() would strip the slash.
        $path = (string) parse_url($request->getRequestUri(), PHP_URL_PATH);

        [$status, $extraHeaders] = $verifier->verify(
            $request->method(),
            $path,
            $headers,
            (string) $request->getContent(),
            time(),
        );

        if ($status !== 200) {
            // 404s stay anonymous and body-less, exactly like the single file.
            return response('', $status)->withHeaders($extraHeaders);
        }

        $inventory = new Inventory($config);
        $health = new Health;

        $root = $inventory->rootPath();
        $framework = $inventory->detectFramework($root);
        $packages = $inventory->packages($root);
        $checks = array_merge(
            $health->level1Checks($root),
            (new Level2)->checksFromApp(app()),
        );

        // The wrapper runs the same modules the single file would,
        // selected by config instead of by stripping code.
        $modules = (new ModuleRunner($config))->run($root, $framework, time());

        $envelope = (new Envelope($config))->build(
            2,
            $framework,
            $packages,
            $checks,
            $health->cliPhpVersion($root),
            $inventory->rootPathSource(),
            [],
            $startedAt,
            (string) (new \ReflectionClass(Config::class))->getFileName(),
            app(CustomChecks::class)->results(),
            $modules,
        );

        return response()->json($envelope);
    }

    private function config(): Config
    {
        $storageDir = storage_path('app/sitetus-collector');

        if (! is_dir($storageDir)) {
            @mkdir($storageDir, 0770, true);
        }

        $config = Config::fromArray([
            'project_key' => (string) config('sitetus-collector.project_key'),
            'public_key' => (string) config('sitetus-collector.public_key'),
            'algo' => (string) config('sitetus-collector.algo', 'ed25519'),
            'expires_at' => config('sitetus-collector.expires_at') ?? '',
            'root_override' => base_path(),
            'nonce_file' => '.nonces.json',
            'modules' => (string) config('sitetus-collector.modules', ''),
        ], $storageDir);

        $config->collectorName = 'sitetus-laravel';

        return $config;
    }
}
