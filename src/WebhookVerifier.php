<?php

declare(strict_types=1);

namespace Sendora;

use Sendora\Events\BouncedEvent;
use Sendora\Events\CapReachedEvent;
use Sendora\Events\CapWarningEvent;
use Sendora\Events\DeferredEvent;
use Sendora\Events\DeliveredEvent;
use Sendora\Events\InboundCapReachedEvent;
use Sendora\Events\InboundCapWarningEvent;
use Sendora\Events\InboundEvent;
use Sendora\Events\SpamComplaintEvent;
use Sendora\Events\UnknownEvent;
use Sendora\Events\UnsubscribedEvent;
use Sendora\Events\WebhookEvent;
use Sendora\Exception\InvalidArgumentException;
use Sendora\Exception\ResponseValidationException;
use Sendora\Exception\WebhookVerificationException;
use Sendora\Internal\JsonObject;
use Sendora\Types\ErrorCode;

/** Verifies a webhook delivery's signature and reads its event. */
final class WebhookVerifier
{
    /** How old a signature may be, in seconds, either way. */
    public const TOLERANCE = 300;

    /** What TypeScript's `trim()` strips in ASCII. PHP's own `trim()` also strips NUL and keeps a form feed. */
    private const WHITESPACE = " \t\n\r\x0B\x0C";

    // \z, since PCRE's $ also matches before a final line break.
    private const SECONDS = '/\A[0-9]{1,18}\z/';

    private const MAC = '/\A[0-9a-fA-F]{64}\z/';

    /**
     * Verifies a delivery's signature and returns its event. Pass the body
     * exactly as it arrived, before any parsing. Answer 2xx quickly and do
     * the work afterwards; deliveries arrive at least once, so key your
     * handling on `$event->id`.
     *
     * ```php
     * try {
     *     $event = WebhookVerifier::verify(
     *         file_get_contents('php://input'),
     *         signature: $_SERVER['HTTP_SENDORA_SIGNATURE'] ?? null,
     *         secret: getenv('SENDORA_WEBHOOK_SECRET') ?: null,
     *     );
     * } catch (WebhookVerificationException) {
     *     http_response_code(400);
     *     exit;
     * }
     * if ($event instanceof BouncedEvent && $event->details->hard) {
     *     markUndeliverable($event->recipient);
     * }
     * ```
     *
     * @param string $body the raw request body
     * @param null|string $signature the `Sendora-Signature` header
     * @param null|string $secret the signing secret that the webhook was created with
     * @param int $tolerance how old a signature may be, in seconds, either way
     * @param null|int $now the current time in seconds since the epoch; tests pin it
     *
     * @return WebhookEvent the event, by its kind; one that this release cannot read is an `UnknownEvent`
     *
     * @throws WebhookVerificationException the signature is missing, malformed or wrong (`invalid_signature`), older or newer than `tolerance` (`stale_signature`), or the signed body is no event (`unexpected_response`)
     * @throws InvalidArgumentException no secret was given, as an unset variable would give, or the tolerance is negative
     */
    public static function verify(
        #[\SensitiveParameter]
        string $body,
        ?string $signature,
        #[\SensitiveParameter]
        ?string $secret,
        int $tolerance = self::TOLERANCE,
        ?int $now = null,
    ): WebhookEvent {
        if ($secret === null || $secret === '') {
            throw new InvalidArgumentException('verify() needs the signing secret that the webhook was created with.');
        }
        if ($tolerance < 0) {
            throw new InvalidArgumentException('tolerance must be zero or more seconds.');
        }
        [$timestamp, $macs] = self::parsed($signature);
        $age = \abs(($now ?? \time()) - $timestamp);
        if ($age > $tolerance) {
            throw new WebhookVerificationException("The signature is {$age} seconds old, more than the tolerance allows.", ErrorCode::StaleSignature->value);
        }
        $expected = \hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
        $matched = false;
        foreach ($macs as $mac) {
            $matched = \hash_equals($expected, \strtolower($mac)) || $matched;
        }
        if (!$matched) {
            throw new WebhookVerificationException('No signature in the header matches the body under this secret.', ErrorCode::InvalidSignature->value);
        }

        return self::event($body);
    }

    /**
     * The header's timestamp, the last one counting, and its signatures.
     *
     * @return array{int, non-empty-list<string>}
     */
    private static function parsed(?string $header): array
    {
        if ($header === null || $header === '') {
            throw new WebhookVerificationException('The request carries no Sendora-Signature header.', ErrorCode::InvalidSignature->value);
        }
        $timestamp = null;
        $macs = [];
        foreach (\explode(',', $header) as $part) {
            $pair = \explode('=', $part, 2);
            if (\count($pair) !== 2) {
                continue;
            }
            $name = \trim($pair[0], self::WHITESPACE);
            $value = \trim($pair[1], self::WHITESPACE);
            if ($name === 't') {
                $timestamp = $value;
            } elseif ($name === 'v1') {
                if (\preg_match(self::MAC, $value) !== 1) {
                    throw self::malformed();
                }
                $macs[] = $value;
            }
        }
        if ($timestamp === null || \preg_match(self::SECONDS, $timestamp) !== 1 || $macs === []) {
            throw self::malformed();
        }

        return [(int) $timestamp, $macs];
    }

    private static function malformed(): WebhookVerificationException
    {
        return new WebhookVerificationException('The Sendora-Signature header is malformed.', ErrorCode::InvalidSignature->value);
    }

    /** The event a verified body holds, read into its class, or an `UnknownEvent`. */
    private static function event(#[\SensitiveParameter] string $body): WebhookEvent
    {
        $json = JsonObject::parse($body);
        if ($json === null || !self::isEvent($json->object)) {
            throw new WebhookVerificationException('The body is signed but is no Sendora event.', ErrorCode::UnexpectedResponse->value);
        }
        try {
            $event = match ($json->object->event) {
                'delivered' => DeliveredEvent::fromWire($json),
                'bounced' => BouncedEvent::fromWire($json),
                'deferred' => DeferredEvent::fromWire($json),
                'spam_complaint' => SpamComplaintEvent::fromWire($json),
                'unsubscribed' => UnsubscribedEvent::fromWire($json),
                'cap_warning' => CapWarningEvent::fromWire($json),
                'cap_reached' => CapReachedEvent::fromWire($json),
                'inbound' => InboundEvent::fromWire($json),
                'inbound_cap_warning' => InboundCapWarningEvent::fromWire($json),
                'inbound_cap_reached' => InboundCapReachedEvent::fromWire($json),
                default => null,
            };
        } catch (ResponseValidationException) {
            $event = null;
        }

        return $event ?? UnknownEvent::fromWire($json);
    }

    /** Whether a payload names its event, its delivery and its attempt, as every event does. */
    private static function isEvent(#[\SensitiveParameter] \stdClass $payload): bool
    {
        return \is_string($payload->event ?? null) && \is_string($payload->id ?? null) && \is_int($payload->attempt ?? null);
    }
}
