<?php

declare(strict_types=1);

use Bliptrace\Php\Transport\Transport;

/**
 * In-memory transport for assertions — captures every shipped envelope
 * instead of making an HTTP call.
 */
final class FakeTransport implements Transport
{
    /** @var array<int,array{endpoint:string,key:string,envelope:array<string,mixed>,timeout:int}> */
    public array $sent = [];

    public function send(string $endpoint, string $key, array $envelope, int $timeout): void
    {
        $this->sent[] = compact('endpoint', 'key', 'envelope', 'timeout');
    }
}
