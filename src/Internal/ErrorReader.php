<?php

declare(strict_types=1);

namespace Sendora\Internal;

use Sendora\Exception\ApiStatusException;
use Sendora\Exception\AuthenticationException;
use Sendora\Exception\BadRequestException;
use Sendora\Exception\ConflictException;
use Sendora\Exception\InternalServerException;
use Sendora\Exception\NotFoundException;
use Sendora\Exception\PermissionDeniedException;
use Sendora\Exception\RateLimitException;
use Sendora\Exception\ResponseValidationException;
use Sendora\Exception\UnprocessableEntityException;
use Sendora\Types\ErrorCode;
use Sendora\Types\LimitScope;
use Sendora\Types\SuppressedRecipient;
use Sendora\Types\ValidationIssue;

/**
 * Reads a refusal into the exception of its status, with every field the
 * API's error body carries. A field it cannot read is left empty rather
 * than hiding the refusal behind a decoding failure.
 *
 * @internal
 */
final class ErrorReader
{
    /** Each `*_exists` code, and the key of the id that already exists. */
    private const EXISTING_IDS = [
        'domain_exists' => 'domainId',
        'webhook_exists' => 'webhookId',
        'stream_exists' => 'streamId',
        'inbound_stream_exists' => 'streamId',
        'inbound_domain_exists' => 'inboundDomainId',
    ];

    /** The largest whole number JavaScript, which writes the API's JSON, sends exactly. */
    private const LARGEST = 9_007_199_254_740_991;

    /**
     * The exception a refusal amounts to. An answer that is not the API's
     * JSON is `unexpected_response`, but for the edge's own plain-text 429
     * and 413, which come before the API sees the request.
     *
     * @param null|JsonObject $body the error body, or `null` when it is no JSON object
     * @param null|string $retryAfter the `Retry-After` header
     */
    public static function fromAnswer(int $status, #[\SensitiveParameter] ?JsonObject $body, ?string $retryAfter): ApiStatusException
    {
        $class = self::classFor($status);
        $wait = self::whole($retryAfter);
        if ($body === null) {
            [$code, $message] = match ($status) {
                429 => [ErrorCode::RateLimited, 'Too many requests reached Sendora at once; its edge refused this one.'],
                413 => [ErrorCode::RequestTooLarge, 'The request body is larger than Sendora accepts; its edge refused it.'],
                default => [ErrorCode::UnexpectedResponse, "The API answered {$status} without an error body."],
            };

            return new $class($message, $code->value, status: $status, retryAfter: $wait);
        }

        $answer = $body->object;
        $code = self::code($answer->error ?? null);
        $issues = self::readable($answer->issues ?? null, ValidationIssue::fromWire(...));
        $message = $answer->message ?? null;
        if (!\is_string($message)) {
            $message = $code === ErrorCode::InvalidRequest->value
                ? 'The request is invalid: ' . \implode('; ', \array_map(
                    static fn(ValidationIssue $issue): string => "{$issue->path}: {$issue->message}",
                    $issues,
                ))
                : "The API answered {$status} without an error message.";
        }
        $missingSubstitution = $code === ErrorCode::SubstitutionMissing->value;

        return new $class(
            $message,
            $code,
            status: $status,
            retryAfter: self::whole($answer->retryAfter ?? null) ?? $wait,
            scope: self::scope($answer->scope ?? null),
            limit: self::whole($answer->limit ?? null),
            cap: self::whole($answer->cap ?? null),
            used: self::whole($answer->used ?? null),
            resetsAt: self::time($answer->resetsAt ?? null),
            max: self::whole($answer->max ?? null) ?? self::whole($answer->maxServers ?? null),
            issues: $issues,
            suppressed: self::readable($answer->suppressed ?? null, SuppressedRecipient::fromWire(...)),
            streamId: $code === ErrorCode::RecipientSuppressed->value ? self::text($answer->streamId ?? null) : null,
            addresses: self::texts($answer->addresses ?? null),
            existingId: self::existingId($code, $answer),
            index: $missingSubstitution ? self::whole($answer->index ?? null) : null,
            keys: $missingSubstitution ? self::texts($answer->keys ?? null) : [],
        );
    }

    /** @return class-string<ApiStatusException> */
    private static function classFor(int $status): string
    {
        if ($status >= 500) {
            return InternalServerException::class;
        }

        return match ($status) {
            400 => BadRequestException::class,
            401 => AuthenticationException::class,
            403 => PermissionDeniedException::class,
            404 => NotFoundException::class,
            409 => ConflictException::class,
            422 => UnprocessableEntityException::class,
            429 => RateLimitException::class,
            default => ApiStatusException::class,
        };
    }

    /** The API's code, which is snake_case; anything else, such as a framework's reason phrase, is not one. */
    private static function code(mixed $raw): string
    {
        return \is_string($raw) && \preg_match('/^[a-z][a-z0-9_]*\z/', $raw) === 1
            ? $raw
            : ErrorCode::UnexpectedResponse->value;
    }

    private static function whole(mixed $value): ?int
    {
        if (\is_string($value) && \preg_match('/^[0-9]{1,16}\z/', $value) === 1) {
            $value = (int) $value;
        }

        return \is_int($value) && $value >= 0 && $value <= self::LARGEST ? $value : null;
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    /** @return list<string> */
    private static function texts(#[\SensitiveParameter] mixed $value): array
    {
        return \is_array($value) ? \array_values(\array_filter($value, \is_string(...))) : [];
    }

    private static function time(mixed $value): ?\DateTimeImmutable
    {
        return \is_string($value) ? Json::parseTime($value) : null;
    }

    private static function scope(mixed $value): ?string
    {
        return \is_string($value) ? LimitScope::tryFrom($value)?->value : null;
    }

    private static function existingId(string $code, #[\SensitiveParameter] \stdClass $answer): ?string
    {
        $key = self::EXISTING_IDS[$code] ?? null;

        return $key === null ? null : self::text($answer->{$key} ?? null);
    }

    /**
     * The entries of a list that read as an answer; the rest are left out.
     *
     * @template T
     *
     * @param \Closure(JsonObject): T $read
     *
     * @return list<T>
     */
    private static function readable(#[\SensitiveParameter] mixed $value, \Closure $read): array
    {
        if (!\is_array($value)) {
            return [];
        }
        $found = [];
        foreach ($value as $entry) {
            if (!$entry instanceof \stdClass) {
                continue;
            }

            try {
                $found[] = $read(new JsonObject($entry));
            } catch (ResponseValidationException) {
                continue;
            }
        }

        return $found;
    }
}
