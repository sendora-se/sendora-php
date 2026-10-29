<?php

declare(strict_types=1);

namespace Sendora\Internal;

use Psr\Log\LoggerInterface;
use Sendora\Exception\ResponseValidationException;
use Sendora\Exception\SendoraException;

/**
 * Makes a call through the transport, repeating it by the retry rules, and
 * hands what it answers back to the resource. An exception from a send
 * carries the send's idempotency key.
 *
 * @internal
 */
final class Caller
{
    /**
     * @param \Closure(): float $random a number in [0, 1) for the jitter; the tests pin it
     * @param null|LoggerInterface $logger records each retry at debug level
     */
    public function __construct(
        private readonly Transport $transport,
        public readonly string $baseUrl,
        private readonly int $maxRetries,
        private readonly Clock $clock,
        private readonly \Closure $random,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /** The same caller over another transport, with another retry count. */
    public function with(Transport $transport, int $maxRetries): self
    {
        return new self($transport, $this->baseUrl, $maxRetries, $this->clock, $this->random, $this->logger);
    }

    /**
     * A call that answers a JSON object, and the answer its decoder reads
     * from it.
     *
     * @template T
     *
     * @param \Closure(JsonObject): T $decode
     *
     * @return T
     */
    public function json(#[\SensitiveParameter] Call $call, #[\SensitiveParameter] \Closure $decode): mixed
    {
        $answer = $this->repeat($call, fn(#[\SensitiveParameter] Reply $reply): JsonObject => Answers::json($reply, $this->baseUrl));

        try {
            return $decode($answer);
        } catch (ResponseValidationException $error) {
            throw $this->carryingKey($call, $error);
        }
    }

    /** A download's bytes. */
    public function bytes(#[\SensitiveParameter] Call $call): string
    {
        return $this->repeat($call, fn(#[\SensitiveParameter] Reply $reply): string => Answers::bytes($reply, $this->baseUrl));
    }

    /** A call that answers nothing. */
    public function nothing(#[\SensitiveParameter] Call $call): void
    {
        $this->repeat($call, function (#[\SensitiveParameter] Reply $reply): void {
            Answers::nothing($reply, $this->baseUrl);
        });
    }

    /**
     * One attempt, and the next one when the retry rules allow it.
     *
     * @template T
     *
     * @param \Closure(Reply): T $read
     *
     * @return T
     */
    private function repeat(#[\SensitiveParameter] Call $call, #[\SensitiveParameter] \Closure $read, int $attempt = 0, float $waited = 0.0): mixed
    {
        try {
            return $read($this->transport->send($call));
        } catch (SendoraException $error) {
            $delay = Retry::delay($error, $attempt, $waited, $this->maxRetries, $call->idempotent, ($this->random)());
            if ($delay === null) {
                throw $this->carryingKey($call, $error);
            }
        }
        $this->logger?->debug(
            'Repeating {method} {path} after {code}, in {delay} s.',
            ['method' => $call->method, 'path' => $call->path, 'code' => $error->errorCode, 'delay' => $delay],
        );
        $this->clock->sleep($delay);

        return $this->repeat($call, $read, $attempt + 1, $waited + $delay);
    }

    /** The original is no argument a trace shows, or a keyed copy's trace would hold a second copy of it. */
    private function carryingKey(#[\SensitiveParameter] Call $call, #[\SensitiveParameter] SendoraException $error): SendoraException
    {
        return $call->idempotencyKey === null ? $error : $error->withIdempotencyKey($call->idempotencyKey);
    }
}
