<?php

declare(strict_types=1);

namespace G8Scope\CakePHP;

use G8Scope\CakePHP\Context\RequestContext;
use G8Scope\Php\Client;
use Throwable;

/**
 * CakePHP reporter: enriches a Throwable with request context and ships it
 * to the ingest API via the runtime {@see Client}. Never throws into the
 * host app.
 */
final class Reporter
{
    public function __construct(
        private readonly Client $client,
    ) {}

    /**
     * Capture an exception, enriching it with the (optional) request context,
     * and ship it immediately. Any failure is swallowed.
     *
     * @param  object|array<string,mixed>|null  $request  A ServerRequest-like object or plain array.
     * @param  array<string,mixed>|null  $user  Optional user context.
     */
    public function capture(Throwable $e, object|array|null $request = null, ?array $user = null): void
    {
        try {
            $envelope = $this->client->buildEnvelope($e, RequestContext::capture($request, $user));
            if ($envelope === null) {
                return;
            }

            $this->client->sendEnvelope($envelope);
        } catch (Throwable) {
            // Never break the host app.
        }
    }

    /**
     * Build the envelope without shipping it — lets the Cake integration
     * defer the send (e.g. on shutdown) so the response is never blocked.
     *
     * @param  object|array<string,mixed>|null  $request
     * @param  array<string,mixed>|null  $user
     * @return array<string,mixed>|null
     */
    public function build(Throwable $e, object|array|null $request = null, ?array $user = null): ?array
    {
        try {
            return $this->client->buildEnvelope($e, RequestContext::capture($request, $user));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Ship a previously-built envelope. Never throws.
     *
     * @param  array<string,mixed>  $envelope
     */
    public function send(array $envelope): void
    {
        try {
            $this->client->sendEnvelope($envelope);
        } catch (Throwable) {
            // Never break the host app.
        }
    }
}
