<?php

declare(strict_types=1);

namespace Sendora\Exception;

use Sendora\Internal\Values;
use Sendora\Types\ErrorCode;
use Sendora\Types\LimitScope;
use Sendora\Types\SuppressedRecipient;
use Sendora\Types\ValidationIssue;

/**
 * What every failed call throws. Catch a subclass for what happened, or
 * compare `errorCode` with an `ErrorCode` value.
 *
 * Neither the key nor the request's subject, text, HTML or attachments is
 * ever part of it. The recipient addresses the API names are in
 * `suppressed` and `addresses` alone.
 *
 * ```php
 * try {
 *     $sendora->email->send(...$message);
 * } catch (RateLimitException $error) {
 *     retryLater(after: $error->retryAfter);
 * } catch (SendoraException $error) {
 *     if ($error->errorCode !== ErrorCode::RecipientSuppressed->value) {
 *         throw $error;
 *     }
 * }
 * ```
 *
 * `getCode()` returns the HTTP status, or 0 when no answer came.
 */
class SendoraException extends \RuntimeException implements ExceptionInterface
{
    /**
     * @param string $message a sentence for a person, as the API wrote it
     * @param string $errorCode what happened, as the API names it; `ErrorCode` lists the values this release knows
     * @param null|int $status the HTTP status, or `null` when no answer came
     * @param null|int $retryAfter seconds to wait before trying again, when the API said so
     * @param null|string $scope whose limit or cap it is, on `rate_limited` and `monthly_cap_reached`
     * @param null|int $limit the emails a minute allowed, on `rate_limited`
     * @param null|int $cap the monthly cap, on `monthly_cap_reached`
     * @param null|int $used what was used of the cap, on `monthly_cap_reached`
     * @param null|\DateTimeImmutable $resetsAt when the month's count starts again, on `monthly_cap_reached`
     * @param null|int $unpaidOre what the account owes for emails before this send, in hundredths of a krona excluding VAT, on `unpaid_limit_reached`
     * @param null|int $limitOre the most the account may owe for emails, in hundredths of a krona excluding VAT, on `unpaid_limit_reached`
     * @param null|int $priceOre this send's price, in hundredths of a krona excluding VAT, on `unpaid_limit_reached`
     * @param null|int $max how many keys, secrets or servers may exist, on `token_limit`, `secret_limit` and `server_limit`
     * @param list<ValidationIssue> $issues one entry per field, on `invalid_request`
     * @param list<SuppressedRecipient> $suppressed the recipients refused, on `recipient_suppressed`
     * @param null|string $streamId the stream whose list refused them, on `recipient_suppressed`
     * @param list<string> $addresses the simulator's addresses a live server refused, on `test_address_on_live_server`
     * @param null|string $existingId the id of what already exists, on the `*_exists` codes
     * @param null|int $index the message of a broadcast that lacks a substitution, on `substitution_missing`
     * @param list<string> $keys the substitutions it lacks, on `substitution_missing`
     * @param null|string $idempotencyKey the key a send went under; repeat the send with it to send nothing twice
     * @param bool $final true when repeating the same request cannot help
     */
    final public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly ?int $status = null,
        public readonly ?int $retryAfter = null,
        #[Values(LimitScope::class)]
        public readonly ?string $scope = null,
        public readonly ?int $limit = null,
        public readonly ?int $cap = null,
        public readonly ?int $used = null,
        public readonly ?\DateTimeImmutable $resetsAt = null,
        public readonly ?int $unpaidOre = null,
        public readonly ?int $limitOre = null,
        public readonly ?int $priceOre = null,
        public readonly ?int $max = null,
        public readonly array $issues = [],
        public readonly array $suppressed = [],
        public readonly ?string $streamId = null,
        public readonly array $addresses = [],
        public readonly ?string $existingId = null,
        public readonly ?int $index = null,
        public readonly array $keys = [],
        public readonly ?string $idempotencyKey = null,
        private readonly bool $final = false,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    /**
     * Whether waiting and calling again could succeed: a rate limit, sending
     * paused for everyone, a charge under way that makes room for unpaid
     * emails, a lost connection, a timeout or a failure on the API's side.
     * Never a refusal that the same request would meet again.
     */
    public function isRetryable(): bool
    {
        if ($this->final) {
            return false;
        }
        $retried = [
            ErrorCode::RateLimited->value,
            ErrorCode::SendingDisabled->value,
            ErrorCode::ConnectionFailed->value,
            ErrorCode::Timeout->value,
        ];
        if (\in_array($this->errorCode, $retried, true)) {
            return true;
        }
        if ($this->errorCode === ErrorCode::UnpaidLimitReached->value) {
            return $this->retryAfter !== null;
        }

        return $this->status !== null && $this->status >= 500;
    }

    /**
     * The same failure, carrying the key its send went under.
     *
     * @internal
     */
    public function withIdempotencyKey(string $idempotencyKey): static
    {
        return new static(
            $this->getMessage(),
            $this->errorCode,
            status: $this->status,
            retryAfter: $this->retryAfter,
            scope: $this->scope,
            limit: $this->limit,
            cap: $this->cap,
            used: $this->used,
            resetsAt: $this->resetsAt,
            unpaidOre: $this->unpaidOre,
            limitOre: $this->limitOre,
            priceOre: $this->priceOre,
            max: $this->max,
            issues: $this->issues,
            suppressed: $this->suppressed,
            streamId: $this->streamId,
            addresses: $this->addresses,
            existingId: $this->existingId,
            index: $this->index,
            keys: $this->keys,
            idempotencyKey: $idempotencyKey,
            final: $this->final,
            previous: $this->getPrevious(),
        );
    }
}
