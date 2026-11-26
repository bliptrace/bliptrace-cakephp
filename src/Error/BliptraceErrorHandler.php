<?php

declare(strict_types=1);

namespace Bliptrace\CakePHP\Error;

use Bliptrace\CakePHP\BliptraceConfig;
use Bliptrace\CakePHP\Reporter;
use Bliptrace\Php\Client;
use Throwable;

/**
 * The CakePHP integration point. Forwards captured exceptions to the
 * {@see Reporter}.
 *
 * In CakePHP 5 this is wired as an `Error.beforeRender` event listener on
 * the application's `Cake\Error\ErrorTrap`, so it sees every exception
 * Cake renders. It deliberately references Cake classes only loosely (via
 * the event object's accessor methods + class_exists guards) so the file is
 * safe to autoload even when `cakephp/cakephp` is absent (e.g. unit tests).
 *
 * Wiring (in src/Application.php — see README):
 *
 *     use Cake\Error\ErrorTrap;
 *     use Bliptrace\CakePHP\Error\BliptraceErrorHandler;
 *
 *     public function bootstrap(): void
 *     {
 *         parent::bootstrap();
 *         (new ErrorTrap(Configure::read('Error')))
 *             ->getEventManager()
 *             ->on('Error.beforeRender', BliptraceErrorHandler::fromConfig(
 *                 Configure::read('Bliptrace', [])
 *             ));
 *     }
 *
 * Or register the handler directly with the global ErrorTrap exposed by
 * `Cake\Error\ErrorTrap::instance()` where available.
 */
final class BliptraceErrorHandler
{
    public function __construct(
        private readonly Reporter $reporter,
    ) {}

    /**
     * Build the handler from a CakePHP-style config array.
     *
     * @param  array<string,mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        $client = new Client(BliptraceConfig::fromArray($config));

        return new self(new Reporter($client));
    }

    /**
     * Invokable so it can be registered directly as an event listener:
     *
     *     $eventManager->on('Error.beforeRender', $handler);
     *
     * Accepts either a Cake `Event` (from which the exception + request are
     * pulled) or a bare Throwable, so it is usable from any error pipeline.
     *
     * @param  object|Throwable  $event  A Cake Event or a Throwable.
     */
    public function __invoke(object $event): void
    {
        try {
            [$exception, $request] = $this->extract($event);
            if ($exception === null) {
                return;
            }

            $this->reporter->capture($exception, $request);
        } catch (Throwable) {
            // Never break the host app.
        }
    }

    /**
     * Pull the exception (and request, if any) out of a Cake Event or a
     * raw Throwable. Uses duck-typing so it never hard-depends on Cake.
     *
     * @return array{0:Throwable|null,1:object|null}
     */
    private function extract(object $event): array
    {
        if ($event instanceof Throwable) {
            return [$event, null];
        }

        $exception = null;
        $request = null;

        // Cake events expose data via getData() / getData($key).
        if (method_exists($event, 'getData')) {
            $candidate = $event->getData('exception') ?? $event->getData('error');
            if ($candidate instanceof Throwable) {
                $exception = $candidate;
            }

            $req = $event->getData('request');
            if (is_object($req)) {
                $request = $req;
            }
        }

        if ($exception === null && method_exists($event, 'getException')) {
            $candidate = $event->getException();
            if ($candidate instanceof Throwable) {
                $exception = $candidate;
            }
        }

        return [$exception, $request];
    }
}
