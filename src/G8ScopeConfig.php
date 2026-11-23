<?php

declare(strict_types=1);

namespace G8Scope\CakePHP;

use G8Scope\Php\Config;

/**
 * Config holder for the CakePHP adapter. Maps a plain CakePHP-style config
 * array (e.g. from `Configure::read('G8Scope')` or an env file) onto the
 * runtime SDK's immutable {@see Config}.
 *
 * The `project` is never part of this config — it is resolved server-side
 * from the ingest key (anti-spoof).
 */
final class G8ScopeConfig
{
    /**
     * Build a runtime {@see Config} from a CakePHP-style config array.
     *
     * Recognised keys (all optional except `key`):
     *  enabled, key, endpoint, environment, release, timeout,
     *  maxFrames, maxMessage, ignoreExceptions, scrubKeys, basePath.
     *
     * @param  array<string,mixed>  $config
     */
    public static function fromArray(array $config): Config
    {
        return new Config(
            key: self::str($config, 'key'),
            endpoint: self::str($config, 'endpoint') ?? 'https://ingest.bliptrace.com/v1/ingest',
            environment: self::str($config, 'environment') ?? 'production',
            release: self::str($config, 'release'),
            timeout: (int) ($config['timeout'] ?? 3),
            maxFrames: (int) ($config['maxFrames'] ?? $config['max_frames'] ?? 30),
            maxMessage: (int) ($config['maxMessage'] ?? $config['max_message'] ?? 2000),
            ignoreExceptions: self::list($config, 'ignoreExceptions', 'ignore_exceptions'),
            scrubKeys: self::list($config, 'scrubKeys', 'scrub_keys'),
            basePath: self::str($config, 'basePath') ?? self::str($config, 'base_path') ?? '',
            enabled: (bool) ($config['enabled'] ?? true),
            sdkName: self::str($config, 'sdkName') ?? 'g8scope-cakephp',
            sdkVersion: self::str($config, 'sdkVersion') ?? '1.0.0',
        );
    }

    /**
     * @param  array<string,mixed>  $config
     */
    private static function str(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * @param  array<string,mixed>  $config
     * @return array<int,string>
     */
    private static function list(array $config, string ...$keys): array
    {
        foreach ($keys as $key) {
            if (isset($config[$key]) && is_array($config[$key])) {
                return array_values(array_map('strval', $config[$key]));
            }
        }

        return [];
    }
}
