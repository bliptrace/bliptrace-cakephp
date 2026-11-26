<?php

declare(strict_types=1);

use Bliptrace\CakePHP\BliptraceConfig;
use Bliptrace\Php\Config;

it('maps a config array onto the runtime Config with defaults', function (): void {
    $config = BliptraceConfig::fromArray(['key' => 'abc']);

    expect($config)->toBeInstanceOf(Config::class)
        ->and($config->key)->toBe('abc')
        ->and($config->endpoint)->toBe('https://ingest.bliptrace.com/v1/ingest')
        ->and($config->environment)->toBe('production')
        ->and($config->enabled)->toBeTrue()
        ->and($config->sdkName)->toBe('bliptrace-cakephp');
});

it('maps explicit values including camelCase and snake_case keys', function (): void {
    $config = BliptraceConfig::fromArray([
        'key' => 'k',
        'endpoint' => 'http://api.test',
        'environment' => 'staging',
        'release' => '1.2.3',
        'timeout' => 5,
        'maxFrames' => 10,
        'max_message' => 500,
        'ignoreExceptions' => [RuntimeException::class],
        'scrub_keys' => ['password', 'token'],
        'basePath' => '/srv/app',
        'enabled' => false,
    ]);

    expect($config->key)->toBe('k')
        ->and($config->endpoint)->toBe('http://api.test')
        ->and($config->environment)->toBe('staging')
        ->and($config->release)->toBe('1.2.3')
        ->and($config->timeout)->toBe(5)
        ->and($config->maxFrames)->toBe(10)
        ->and($config->maxMessage)->toBe(500)
        ->and($config->ignoreExceptions)->toBe([RuntimeException::class])
        ->and($config->scrubKeys)->toBe(['password', 'token'])
        ->and($config->basePath)->toBe('/srv/app')
        ->and($config->enabled)->toBeFalse();
});

it('treats an empty key as null', function (): void {
    expect(BliptraceConfig::fromArray(['key' => ''])->key)->toBeNull();
});
