<?php

declare(strict_types=1);

namespace Bliptrace\CakePHP\Context;

/**
 * Builds the envelope context from the current CakePHP request (if any).
 *
 * Accepts a Cake `ServerRequest`-like object (anything exposing
 * `getMethod()`, `getRequestTarget()` / `getUri()`, `getHeaders()`),
 * a plain array of request data, or null — so it is testable without
 * loading CakePHP. Returns `['custom' => []]` when no request is present.
 */
final class RequestContext
{
    /** Headers that must never leave the host app. */
    private const STRIPPED_HEADERS = ['authorization', 'cookie'];

    /**
     * @param  object|array<string,mixed>|null  $request  A ServerRequest-like object or a plain array.
     * @param  array<string,mixed>|null  $user  Optional user context (e.g. ['id' => '123']).
     * @return array<string,mixed>
     */
    public static function capture(object|array|null $request = null, ?array $user = null): array
    {
        $context = ['custom' => []];

        $data = self::normalise($request);
        if ($data === null) {
            if ($user !== null && $user !== []) {
                $context['user'] = $user;
            }

            return $context;
        }

        $context['request'] = [
            'method' => $data['method'],
            'url' => $data['url'],
            'headers' => self::stripHeaders($data['headers']),
        ];

        $resolvedUser = $user ?? $data['user'];
        if ($resolvedUser !== null && $resolvedUser !== []) {
            $context['user'] = $resolvedUser;
        }

        return $context;
    }

    /**
     * @param  object|array<string,mixed>|null  $request
     * @return array{method:string,url:string,headers:array<string,mixed>,user:array<string,mixed>|null}|null
     */
    private static function normalise(object|array|null $request): ?array
    {
        if ($request === null) {
            return null;
        }

        if (is_array($request)) {
            if ($request === []) {
                return null;
            }

            return [
                'method' => (string) ($request['method'] ?? 'GET'),
                'url' => (string) ($request['url'] ?? ''),
                'headers' => is_array($request['headers'] ?? null) ? $request['headers'] : [],
                'user' => is_array($request['user'] ?? null) ? $request['user'] : null,
            ];
        }

        // ServerRequest-like (PSR-7 / CakePHP\Http\ServerRequest).
        if (! method_exists($request, 'getMethod')) {
            return null;
        }

        return [
            'method' => (string) $request->getMethod(),
            'url' => self::url($request),
            'headers' => method_exists($request, 'getHeaders') ? (array) $request->getHeaders() : [],
            'user' => null,
        ];
    }

    private static function url(object $request): string
    {
        if (method_exists($request, 'getUri')) {
            $uri = $request->getUri();
            if (is_object($uri) && method_exists($uri, '__toString')) {
                return (string) $uri;
            }
        }

        if (method_exists($request, 'getRequestTarget')) {
            return (string) $request->getRequestTarget();
        }

        return '';
    }

    /**
     * @param  array<string,mixed>  $headers
     * @return array<string,string>
     */
    private static function stripHeaders(array $headers): array
    {
        $clean = [];
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), self::STRIPPED_HEADERS, true)) {
                continue;
            }
            $clean[(string) $name] = is_array($value) ? implode(',', $value) : (string) $value;
        }

        return $clean;
    }
}
