<?php

declare(strict_types=1);

namespace Sendora\Types;

/**
 * Every code this release knows: those the API answers, then the SDK's own
 * for a call that got no proper answer and for a webhook that does not
 * verify. An exception's `errorCode` is a string, so a code newer than
 * this release still comes through; compare it with a case's `value`.
 */
enum ErrorCode: string
{
    case InvalidRequest = 'invalid_request';
    case Unauthorized = 'unauthorized';
    case WrongTokenKind = 'wrong_token_kind';
    case NotForConnections = 'not_for_connections';
    case ReadOnlyConnection = 'read_only_connection';
    case TestServerOnly = 'test_server_only';
    case PaymentRequired = 'payment_required';
    case AccountPaused = 'account_paused';
    case AccountNotActive = 'account_not_active';
    case SpamComplaintLocked = 'spam_complaint_locked';
    case UnsubscribeLocked = 'unsubscribe_locked';
    case NotFound = 'not_found';
    case DomainExists = 'domain_exists';
    case DomainReserved = 'domain_reserved';
    case InboundDomainExists = 'inbound_domain_exists';
    case WebhookExists = 'webhook_exists';
    case StreamExists = 'stream_exists';
    case InboundStreamExists = 'inbound_stream_exists';
    case DefaultStream = 'default_stream';
    case LastToken = 'last_token';
    case TokenLimit = 'token_limit';
    case LastSecret = 'last_secret';
    case SecretLimit = 'secret_limit';
    case ServerExists = 'server_exists';
    case ServerLimit = 'server_limit';
    case ServerReserved = 'server_reserved';
    case ServerInFlight = 'server_in_flight';
    case RequestTooLarge = 'request_too_large';
    case FromDomainNotVerified = 'from_domain_not_verified';
    case StreamNotFound = 'stream_not_found';
    case StreamArchived = 'stream_archived';
    case StreamPaused = 'stream_paused';
    case StreamNotSendable = 'stream_not_sendable';
    case StreamNotBroadcast = 'stream_not_broadcast';
    case BroadcastNotOpen = 'broadcast_not_open';
    case SubstitutionMissing = 'substitution_missing';
    case UnsubscribePlaceholderMissing = 'unsubscribe_placeholder_missing';
    case ListUnsubscribeReserved = 'list_unsubscribe_reserved';
    case ContentExpired = 'content_expired';
    case ContentUnreadable = 'content_unreadable';
    case RecipientSuppressed = 'recipient_suppressed';
    case TestAddressOnLiveServer = 'test_address_on_live_server';
    case ModeImmutable = 'mode_immutable';
    case StreamKindNotAllowed = 'stream_kind_not_allowed';
    case IdempotencyKeyMismatch = 'idempotency_key_mismatch';
    case IdempotencyKeyRequired = 'idempotency_key_required';
    case RateLimited = 'rate_limited';
    case MonthlyCapReached = 'monthly_cap_reached';
    case SendingDisabled = 'sending_disabled';

    case ConnectionFailed = 'connection_failed';
    case Timeout = 'timeout';
    case UnexpectedResponse = 'unexpected_response';
    case InvalidSignature = 'invalid_signature';
    case StaleSignature = 'stale_signature';
}
