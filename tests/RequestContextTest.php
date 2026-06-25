<?php

declare(strict_types=1);

use G8Scope\CakePHP\Context\RequestContext;

it('yields only custom when there is no request', function (): void {
    expect(RequestContext::capture(null))->toBe(['custom' => []]);
    expect(RequestContext::capture([]))->toBe(['custom' => []]);
});

it('captures method and url from an array request', function (): void {
    $context = RequestContext::capture([
        'method' => 'GET',
        'url' => 'https://app.test/dashboard',
        'headers' => ['Accept' => 'text/html'],
    ]);

    expect($context['request']['method'])->toBe('GET')
        ->and($context['request']['url'])->toBe('https://app.test/dashboard')
        ->and($context['request']['headers'])->toBe(['Accept' => 'text/html'])
        ->and($context['custom'])->toBe([]);
});

it('strips authorization and cookie headers (case-insensitive)', function (): void {
    $context = RequestContext::capture([
        'method' => 'POST',
        'url' => '/x',
        'headers' => [
            'AUTHORIZATION' => 'Bearer t',
            'cookie' => 'a=b',
            'Cookie' => 'c=d',
            'X-Request-Id' => 'abc',
        ],
    ]);

    expect($context['request']['headers'])->toBe(['X-Request-Id' => 'abc']);
});

it('flattens array header values', function (): void {
    $context = RequestContext::capture([
        'method' => 'GET',
        'url' => '/x',
        'headers' => ['Accept' => ['application/json', 'text/html']],
    ]);

    expect($context['request']['headers']['Accept'])->toBe('application/json,text/html');
});

it('includes user context when provided', function (): void {
    $context = RequestContext::capture(['method' => 'GET', 'url' => '/x'], ['id' => '42']);

    expect($context['user'])->toBe(['id' => '42']);
});

it('reads from a ServerRequest-like object', function (): void {
    $request = new class
    {
        public function getMethod(): string
        {
            return 'PUT';
        }

        public function getUri(): object
        {
            return new class
            {
                public function __toString(): string
                {
                    return 'https://app.test/api/items/1';
                }
            };
        }

        /** @return array<string,array<int,string>> */
        public function getHeaders(): array
        {
            return [
                'Authorization' => ['Bearer secret'],
                'Accept' => ['application/json'],
            ];
        }
    };

    $context = RequestContext::capture($request);

    expect($context['request']['method'])->toBe('PUT')
        ->and($context['request']['url'])->toBe('https://app.test/api/items/1')
        ->and($context['request']['headers'])->toBe(['Accept' => 'application/json']);
});
