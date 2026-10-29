# Changelog

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
