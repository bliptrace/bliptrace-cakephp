<?php

declare(strict_types=1);

use G8Scope\CakePHP\G8ScopeConfig;
use G8Scope\CakePHP\Reporter;
use G8Scope\Php\Client;
use G8Scope\Php\Transport\Transport;

function reporterWith(FakeTransport $t, array $overrides = []): Reporter
{
    $config = G8ScopeConfig::fromArray([
        'key' => array_key_exists('key', $overrides) ? $overrides['key'] : 'test-key',
        'endpoint' => $overrides['endpoint'] ?? 'http://api.test',
        'enabled' => $overrides['enabled'] ?? true,
        'ignoreExceptions' => $overrides['ignore_exceptions'] ?? [],
    ]);

    return new Reporter(new Client($config, $t));
}

it('builds and ships a valid envelope for an exception', function (): void {
    $t = new FakeTransport;
    reporterWith($t)->capture(new RuntimeException('boom'));

    expect($t->sent)->toHaveCount(1);

    $envelope = $t->sent[0]['envelope'];
    expect($t->sent[0]['endpoint'])->toBe('http://api.test')
        ->and($t->sent[0]['key'])->toBe('test-key')
        ->and($envelope['exception']['type'])->toBe('RuntimeException')
        ->and($envelope['exception']['message'])->toBe('boom')
        ->and($envelope['runtime']['name'])->toBe('php')
        ->and($envelope['sdk']['name'])->toBe('g8scope-cakephp');
});

it('enriches the envelope with request context (stripping secrets)', function (): void {
    $t = new FakeTransport;
    reporterWith($t)->capture(
        new RuntimeException('boom'),
        [
            'method' => 'POST',
            'url' => 'https://app.test/checkout',
            'headers' => [
                'Authorization' => 'Bearer secret',
                'Cookie' => 'session=abc',
                'Accept' => 'application/json',
            ],
        ],
    );

    $request = $t->sent[0]['envelope']['context']['request'];
    expect($request['method'])->toBe('POST')
        ->and($request['url'])->toBe('https://app.test/checkout')
        ->and($request['headers'])->toHaveKey('Accept')
        ->and($request['headers'])->not->toHaveKey('Authorization')
        ->and($request['headers'])->not->toHaveKey('Cookie');
});

it('skips ignored exception types', function (): void {
    $t = new FakeTransport;
    reporterWith($t, ['ignore_exceptions' => [InvalidArgumentException::class]])
        ->capture(new InvalidArgumentException('nope'));

    expect($t->sent)->toBeEmpty();
});

it('does not ship when the master kill switch is off', function (): void {
    $t = new FakeTransport;
    reporterWith($t, ['enabled' => false])->capture(new Exception('x'));

    expect($t->sent)->toBeEmpty();
});

it('does not ship when no ingest key is configured', function (): void {
    $t = new FakeTransport;
    reporterWith($t, ['key' => null])->capture(new Exception('x'));

    expect($t->sent)->toBeEmpty();
});

it('never throws even when the transport fails', function (): void {
    $throwing = new class implements Transport
    {
        public function send(string $endpoint, string $key, array $envelope, int $timeout): void
        {
            throw new RuntimeException('transport down');
        }
    };

    $reporter = new Reporter(new Client(G8ScopeConfig::fromArray(['key' => 'k']), $throwing));

    expect(fn () => $reporter->capture(new Exception('boom')))->not->toThrow(Throwable::class);
});

it('can build then send an envelope separately (deferred)', function (): void {
    $t = new FakeTransport;
    $reporter = reporterWith($t);

    $envelope = $reporter->build(new RuntimeException('later'));
    expect($t->sent)->toBeEmpty()
        ->and($envelope)->not->toBeNull();

    $reporter->send($envelope);
    expect($t->sent)->toHaveCount(1)
        ->and($t->sent[0]['envelope']['exception']['message'])->toBe('later');
});
