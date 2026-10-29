# Changelog

## 0.6.0

- `SendoraAccount`, the client for an account key. It reads the key
  from `SENDORA_ACCOUNT_TOKEN` when not given, and takes the same
  settings as `Sendora`.
- `$account->servers`: `create()` makes a live or test server and
  returns its first key, shown this once. `list()` reads every server,
  `get()` one, `update()` renames one, and `delete()` removes one.
- `$account->servers->tokens`: `create()`, `list()`, `get()` and
  `revoke()` manage the keys of any of the account's servers.
- `Keys::isTestKey()` tells a test server's key from a live one's.
- `Server`, `CreatedServer` and `ServerList` in `Sendora\Types`.
  `var_dump()`, `print_r()` and `json_encode()` of a `CreatedServer`
  leave out its first key's value.

## 0.5.0

- `$sendora->inbound`: `search()` reads a page of the mail that the
  server's inbound streams received, and `searchAll()` walks every page.
  `get()` reads one message with its parties, headers, text and HTML.
  `raw()` downloads the message byte for byte, and `attachment()` one of
  its attachments.
- `$sendora->inboundDomains`: `create()` claims a domain for an inbound
  stream and returns the MX and TXT records to create. `verify()` looks
  them up now, `list()` reads every domain, `get()` one, and `delete()`
  removes one.
- `InboundMessage`, `InboundMessageDetail`, `InboundPage`,
  `InboundAddress`, `InboundAuthentication`, `InboundEnvelope`,
  `InboundHeader`, `InboundMessageAttachment`, `InboundDomain`,
  `VerifiedInboundDomain`, `InboundDomainList`, `InboundDomainCheck`,
  `MxRecord`, `TxtRecord`, `SpfVerdict`, `AuthVerdict`, `DmarcPolicy`,
  `ArcVerdict` and `RecordCheck` in `Sendora\Types`.

## 0.4.0

- `$sendora->webhooks`: `create()` registers a URL for the server's
  events and returns its signing secret, shown this once. `list()` reads
  every webhook, `get()` one, and `delete()` removes one.
  `deliveries()` reads a page of a webhook's deliveries,
  `deliveriesAll()` walks every page, and `replay()` queues one again.
  `createSecret()` adds a second signing secret, and `deleteSecret()`
  removes one, so that a secret can be rolled without a gap.
- `Webhook`, `CreatedWebhook`, `WebhookList`, `WebhookSecret`,
  `CreatedWebhookSecret`, `Delivery`, `DeliveryPage`, `WebhookEventName`,
  `InboundContent` and `DeliveryStatus` in `Sendora\Types`. `var_dump()`,
  `print_r()` and `json_encode()` of a `CreatedWebhook` or a
  `CreatedWebhookSecret` leave out the secret.

## 0.3.0

- `$sendora->suppressions`: `list()` reads a page of a stream's
  suppression list, and `listAll()` walks every page. `delete()` lifts a
  hard bounce or an entry made by hand, so that the server may send to
  the address again.
- `$sendora->tokens`: `create()` makes another key of the server and
  returns its value, shown this once. `list()` reads every key, `get()`
  one, and `revoke()` stops a key working at once.
- `$sendora->server`: `get()` reads the key's own server: its mode, its
  limits with their use, its inbound cap and the domains it may send
  from.
- `Suppression`, `SuppressionPage`, `Token`, `CreatedToken`, `TokenList`,
  `OwnServer`, `ServerLimit`, `InboundCap`, `SendableDomain`, `ServerMode`
  and `LimitPeriod` in `Sendora\Types`. `var_dump()`, `print_r()` and
  `json_encode()` of a `CreatedToken` leave out the key's value.

## 0.2.0

- `$sendora->streams`: `create()` adds a transactional, broadcast or
  inbound stream, `list()` reads every stream of the server, and `get()`
  one. `update()` renames a stream, or sets how long an inbound stream
  keeps received content, and `null` puts that back to the default.
  `archive()` stops a stream taking new messages.
- `$sendora->broadcasts`: `send()` stores a broadcast of up to 50,000
  messages under an idempotency key, each with its recipient's own
  unsubscribe link. `list()` reads a page of the server's broadcasts,
  `get()` one with its progress, and `cancel()` holds back the messages
  not yet handed over.
- `Stream`, `StreamList`, `StreamKind`, `AcceptedBroadcast`, `Broadcast`,
  `BroadcastPage` and `BroadcastStatus` in `Sendora\Types`.

## 0.1.0

- `Sendora`, the client for a server key. It reads the key from
  `SENDORA_API_TOKEN` and the address from `SENDORA_BASE_URL` when not
  given. It sends through curl, or through a PSR-18 client of your own,
  and repeats what is safe to repeat. `withOptions()` makes a copy with
  another timeout or retry count, over the same connection.
- `$sendora->email`: `send()` hands one message over for delivery, and
  `sendBatch()` up to 100, each under an idempotency key so that a retry
  never sends twice. A batch answers each message on its own, as an
  `AcceptedBatchItem` or a `RefusedBatchItem`.
- `$sendora->messages`: `get()` reads one message with its recipients,
  attachments and events. `search()` reads a page of the message log, and
  `searchAll()` walks every page.
- The answers of these calls in `Sendora\Types`, and the enums that list
  the values their fixed sets hold in this release.
- `SendoraException` and a class for each kind of failure under it,
  `ExceptionInterface` over everything the SDK throws, and `ErrorCode`,
  which lists the codes this release knows.
- `Attachment`, which encodes a file's bytes for a send.
