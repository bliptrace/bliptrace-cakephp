# g8scope CakePHP SDK

A thin CakePHP adapter for [g8scope](https://scope.g8suite.com) — it auto-captures
exceptions from your CakePHP application and ships them to the g8scope ingest API.

It is a lightweight integration over the runtime [`bliptrace/php`](../bliptrace-php)
client: a Cake error-handling hook, config glue, request-context enrichment and
a Reporter. It **never throws into the host app** — a slow or unavailable ingest
endpoint will never break your application.

> The **project** is resolved server-side from the ingest key. It is never sent
> in the payload (anti-spoof).

## Installation

```bash
composer require bliptrace/cakephp
```

Requires PHP 8.2+ and CakePHP 5.x.

## Configuration

Add a `G8Scope` block to your CakePHP configuration (e.g. `config/app_local.php`
or via `Configure::write()`), reading secrets from the environment:

```php
// config/app_local.php
use G8Scope\Php\Env; // BLIPTRACE_<NAME>, falling back to G8SCOPE_<NAME>

return [
    'G8Scope' => [
        'enabled' => filter_var(Env::get('ENABLED', 'true'), FILTER_VALIDATE_BOOL),
        'key' => Env::get('KEY'),
        'endpoint' => Env::get('ENDPOINT', 'https://ingest.scope.g8suite.com'),
        'environment' => Env::get('ENVIRONMENT', 'production'),
        'release' => Env::get('RELEASE'),
        'timeout' => 3,
        'maxFrames' => 30,
        'maxMessage' => 2000,
        'basePath' => ROOT, // marks your own frames as in-app
        'ignoreExceptions' => [
            \Cake\Http\Exception\NotFoundException::class,
            \Cake\Http\Exception\MethodNotAllowedException::class,
        ],
        'scrubKeys' => ['password', 'token', 'secret', 'authorization'],
    ],
];
```

> **Env names:** `G8Scope\Php\Env::get('KEY')` (from `bliptrace/php` ^1.2) reads `BLIPTRACE_KEY` and
> falls back to the deprecated `G8SCOPE_KEY` when it is unset (fallback removed in 2.0). Rename your
> variables to `BLIPTRACE_*`; `G8SCOPE_ENV` becomes `BLIPTRACE_ENVIRONMENT`.

| Key | Default | Notes |
|---|---|---|
| `enabled` | `true` | Master kill switch. |
| `key` | `null` | Ingest key. No key → nothing is shipped. |
| `endpoint` | `https://ingest.scope.g8suite.com` | Ingest base URL. Posts to `{endpoint}/v1/ingest` with `X-Ingest-Key`. |
| `environment` | `production` | |
| `release` | `null` | Version/commit of your deploy. |
| `timeout` | `3` | Transport timeout (seconds). |
| `maxFrames` | `30` | Max stack frames captured. |
| `maxMessage` | `2000` | Max message length. |
| `basePath` | `''` | App root; frames under it (and not in `/vendor/`) are flagged in-app. |
| `ignoreExceptions` | `[]` | Fully-qualified exception classes to skip. |
| `scrubKeys` | `[]` | Context keys to scrub before sending. |

## Wiring into CakePHP

CakePHP 5 routes every rendered exception through `Cake\Error\ErrorTrap`, which
dispatches an `Error.beforeRender` event. Register the g8scope handler against
that event in your `src/Application.php`:

```php
use Cake\Core\Configure;
use Cake\Error\ErrorTrap;
use Cake\Event\EventManager;
use G8Scope\CakePHP\Error\G8ScopeErrorHandler;

public function bootstrap(): void
{
    parent::bootstrap();

    $handler = G8ScopeErrorHandler::fromConfig(Configure::read('G8Scope', []));

    // Listen on the global event manager so any ErrorTrap instance is covered.
    EventManager::instance()->on('Error.beforeRender', $handler);
}
```

Alternatively, attach it to the `ErrorTrap` you build in middleware:

```php
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Error\ErrorTrap;

$errorTrap = new ErrorTrap(Configure::read('Error'));
$errorTrap->getEventManager()->on(
    'Error.beforeRender',
    G8ScopeErrorHandler::fromConfig(Configure::read('G8Scope', []))
);
$middlewareQueue->add(new ErrorHandlerMiddleware($errorTrap));
```

The handler pulls the exception (and the request, if present) out of the event,
strips `Authorization`/`Cookie` headers, builds the envelope and ships it. Any
failure inside the handler is swallowed.

## Manual capture

You can also report exceptions yourself — useful for caught/handled errors:

```php
use Cake\Core\Configure;
use G8Scope\CakePHP\G8ScopeConfig;
use G8Scope\CakePHP\Reporter;
use G8Scope\Php\Client;

$reporter = new Reporter(new Client(
    G8ScopeConfig::fromArray(Configure::read('G8Scope', []))
));

try {
    riskyOperation();
} catch (\Throwable $e) {
    // Pass the current request (a Cake ServerRequest) for context, plus optional user.
    $reporter->capture($e, $this->request, ['id' => (string) $userId]);
}
```

To avoid blocking the response, build the envelope during the request and ship
it on shutdown:

```php
$envelope = $reporter->build($e, $request);
register_shutdown_function(fn () => $reporter->send($envelope));
```

## Testing

This package's own tests depend only on `bliptrace/php` (no CakePHP), so they run
fast. The Cake-specific class guards its CakePHP references via duck-typing and
is safe to autoload without `cakephp/cakephp` installed.

```bash
composer install
composer test
```

## Licence

MIT. See [LICENSE](LICENSE). British spelling throughout.
