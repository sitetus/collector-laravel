<?php

namespace Sitetus\LaravelCollector\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sitetus\Collector\RequestVerifier;
use Sitetus\LaravelCollector\SitetusServiceProvider;

abstract class TestCase extends Orchestra
{
    protected const INSTALL_PATH = 'sitetus-wraptest';

    protected const PROJECT_KEY = 'wrapperproject123456';

    protected static string $secretKey = '';

    protected static string $publicKey = '';

    protected function getPackageProviders($app): array
    {
        return [SitetusServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        if (static::$secretKey === '') {
            $pair = sodium_crypto_sign_keypair();
            static::$secretKey = base64_encode(sodium_crypto_sign_secretkey($pair));
            static::$publicKey = base64_encode(sodium_crypto_sign_publickey($pair));
        }

        $app['config']->set('sitetus-collector', [
            'enabled' => true,
            'project_key' => self::PROJECT_KEY,
            'public_key' => static::$publicKey,
            'algo' => 'ed25519',
            'expires_at' => null,
            'install_path' => self::INSTALL_PATH,
            // Mirrors the package default; defineEnvironment
            // replaces the whole array, so mergeConfigFrom cannot fill it.
            'modules' => 'files,archives,permissions',
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function signedHeaders(string $path, ?int $timestamp = null): array
    {
        $timestamp = (string) ($timestamp ?? time());
        $nonce = bin2hex(random_bytes(16));

        $material = RequestVerifier::signatureMaterial('GET', $path, self::PROJECT_KEY, $timestamp, $nonce, '');
        $signature = sodium_crypto_sign_detached($material, (string) base64_decode(static::$secretKey, true));

        return [
            'X-Sitetus-Key' => self::PROJECT_KEY,
            'X-Sitetus-Timestamp' => $timestamp,
            'X-Sitetus-Nonce' => $nonce,
            'X-Sitetus-Algo' => 'ed25519',
            'X-Sitetus-Signature' => base64_encode($signature),
        ];
    }
}
