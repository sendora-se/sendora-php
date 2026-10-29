# Changelog

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
