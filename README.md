# sendora/sendora-php

The PHP client for [Sendora](https://sendora.se), transactional email
delivery. PHP 8.2 or newer, with ext-curl and ext-json and no other
package. Typed throughout: every argument is named and typed for your
editor and PHPStan, and every answer is a readonly class.

```sh
composer require sendora/sendora-php
```

In a Laravel app, install `sendora/sendora-laravel` instead. It installs
this package, sends Laravel's mail with `MAIL_MAILER=sendora`, and takes
webhooks on a route of its own, with a Laravel event for each.

Two kinds of key, two clients. `Sendora` takes a server key (`sk_…`) and
does everything inside one server: sending, broadcasts, the log, received
mail, streams, suppressions, tokens, webhooks and the server's own limits.
`SendoraAccount` takes an account key (`ak_…`) and manages the account:
its servers, their keys and its sending domains. Both keys are secrets:
keep them on the server and never ship them to a browser.

```php
use Sendora\Attachment;
use Sendora\Sendora;

$sendora = new Sendora(); // the key from SENDORA_API_TOKEN

$accepted = $sendora->email->send(
    from: ['email' => 'no-reply@example.se', 'name' => 'Example AB'],
    to: ['anna@example.com'],
    subject: 'Din faktura för september',
    text: 'Hej Anna, fakturan finns bifogad.',
    attachments: [Attachment::fromFile('faktura.pdf', contentType: 'application/pdf')],
    tag: 'invoice',
    metadata: ['invoice_id' => '2026-0912'],
);
echo $accepted->messageId;
```

A client without a key throws `InvalidArgumentException` at construction,
so build it at startup. `new Sendora('sk_…')` takes the key directly, and
`new SendoraAccount()` reads `SENDORA_ACCOUNT_TOKEN`. The SDK reads a
variable from `$_SERVER`, then `$_ENV`, then `getenv()`, so a framework's
`.env` loader works. PHP-FPM clears its workers' environment by default,
so pass the key in, or set it in the pool with `env[SENDORA_API_TOKEN]`.
Make one client per process and share it. It reuses its connection and
needs no closing. `var_dump()` and `print_r()` of a client show its
`baseUrl`, never the key.

Both clients take the same arguments, each of them optional:

| Argument                          | Default                                                              | What it is                                                    |
| --------------------------------- | -------------------------------------------------------------------- | ------------------------------------------------------------- |
| `token`                           | `SENDORA_API_TOKEN`, or `SENDORA_ACCOUNT_TOKEN` for `SendoraAccount` | The key                                                       |
| `baseUrl`                         | `SENDORA_BASE_URL`, then `https://api.sendora.se`                    | The API's address: https, or http to a loopback address alone |
| `timeout`                         | 30                                                                   | The seconds a whole attempt may take over curl                |
| `maxRetries`                      | 2                                                                    | How often a failed call is repeated; 0 turns retries off      |
| `httpClient`                      | curl                                                                 | A PSR-18 client of your own                                   |
| `requestFactory`, `streamFactory` | The client itself, when it implements PSR-17                         | The PSR-17 factories that build the client's requests         |
| `logger`                          | None                                                                 | A PSR-3 logger, which records each retry                      |

`from` must be on a verified sending domain. `to`, `cc` and `bcc` take
plain addresses or `['email' => …, 'name' => …]` arrays, at most 50 in
all, and at least one of `text` and `html` is required. `streamId` names
the stream the message goes on; without it the default transactional
stream. An attachment is `Attachment::fromFile()` or
`Attachment::fromBytes()`, or an array whose `content` is already base64.
`fromFile()` names it after its file unless `name:` says otherwise.

Every method takes what its route takes, with the API's camelCase names,
and returns what the route returns. A path's ids come first, by position.
Name every argument after them, as the examples do. Your own keys, in
`metadata`, `headers` and `substitutions`, are sent as you wrote them,
keys of digits included.

## Sending

| Call                                    | Returns                                                              |
| --------------------------------------- | -------------------------------------------------------------------- |
| `$sendora->email->send(...$message)`    | `AcceptedEmail` with `messageId`, `status`, `submittedAt` and `test` |
| `$sendora->email->sendBatch($messages)` | `BatchResult` with `results`, one per message in order; 100 at most  |

`accepted` means the message is stored and on its way to the mail server.
Delivery, deferral, bounce and complaint arrive later as events on the
message and as webhooks.

`send()` also takes a message as an array with the same keys as its
arguments, spread with `...`, and so does each item of `sendBatch()`.
PHPStan knows the array as `EmailMessage`: import it with
`@phpstan-import-type EmailMessage from Email`.

`idempotencyKey:` names the request so a retry cannot send twice. The SDK
makes a random UUID when you give none. Give your own when a retry may
come from another process, such as a queued job. The API remembers a key
for 24 hours and refuses it with a different body
(`idempotency_key_mismatch`). An exception from a send carries the key it
went under as `$error->idempotencyKey`.

A batch never throws for one refused message. Each result is an
`AcceptedBatchItem` with the `messageId`, or a `RefusedBatchItem` with
the `errorCode` and fields that a single send would throw. Both have
`index`, the message's place in the list, counted from 0. A retried batch
returns the items sent before with `replayed` set.

```php
use Sendora\Types\RefusedBatchItem;

$batch = $sendora->email->sendBatch($messages, idempotencyKey: 'invoices-2026-09');
foreach ($batch->results as $result) {
    if ($result instanceof RefusedBatchItem) {
        echo $result->index, ' ', $result->errorCode, ' ', $result->message, "\n";
    }
}
```

## Messages

| Call                                                              | Returns                                                                                    |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| `$sendora->messages->get($messageId)`                             | `MessageDetail`: the message with its recipients, its attachments described and its events |
| `$sendora->messages->search(...$filters)`                         | `MessagePage` with `messages` and `next`, newest first                                     |
| `$sendora->messages->searchAll(...$filters)`                      | Every match, page by page, as a generator                                                  |
| `$sendora->inbound->get($inboundMessageId)`                       | A received message: envelope, parties, headers, text, HTML, attachments described          |
| `$sendora->inbound->search(...$filters)`                          | `InboundPage` of received mail, newest first                                               |
| `$sendora->inbound->searchAll(...$filters)`                       | Every received match, page by page, as a generator                                         |
| `$sendora->inbound->raw($inboundMessageId)`                       | The raw message's bytes as a string, `message/rfc822`                                      |
| `$sendora->inbound->attachment($inboundMessageId, $attachmentId)` | An attachment's bytes as a string                                                          |

A stream can also receive on a domain of yours: every address on it,
whatever the local part, lands on the stream. Claim it with the stream
id, and add the two records the answer gives: an MX that names Sendora
and a TXT record that proves the claim. Mail is accepted once both are
seen. A stream holds one domain, and the first account to verify a name
holds it.

| Call                                                   | Returns                                                                      |
| ------------------------------------------------------ | ---------------------------------------------------------------------------- |
| `$sendora->inboundDomains->create(streamId:, domain:)` | `InboundDomain` with `mx` and `txt` to add                                   |
| `$sendora->inboundDomains->list()`                     | `InboundDomainList` with `domains`                                           |
| `$sendora->inboundDomains->get($inboundDomainId)`      | One domain with the state of its records                                     |
| `$sendora->inboundDomains->verify($inboundDomainId)`   | The domain plus `check` per record: `ok`, `missing`, `mismatch`, `dns_error` |
| `$sendora->inboundDomains->delete($inboundDomainId)`   | Nothing; mail to it is refused from then on                                  |

The filters of `messages->search()` are `recipient`, a whole address or
three characters or more of one, and `streamId`, `broadcastId` and `tag`.
`status` takes `queued`, `delivered`, `deferred`, `bounced` or `expired`,
or a list of them. `from` and `to` take a `DateTimeInterface` or RFC 3339
text. `limit` is 1 to 100, 50 by default, and `after` is the previous
page's `next`. Bodies are never returned, and the log keeps messages for
13 months.

The filters of `inbound->search()` are `streamId`, and `from`, the
sender's address. `recipient` is the address of yours that the mail went
to, and `mailboxHash` the text after a plus sign in it. `receivedFrom`
and `receivedTo` take times, and `limit` and `after` work as above.

```php
use Sendora\Types\MessageRecipient;
use Sendora\Types\RecipientStatus;

$message = $sendora->messages->get($messageId);
$waiting = array_filter(
    $message->recipients,
    fn(MessageRecipient $recipient): bool => $recipient->status !== RecipientStatus::Delivered->value,
);
echo count($waiting), " recipients not delivered yet\n";

foreach ($sendora->messages->searchAll(status: 'bounced', from: new DateTimeImmutable('2026-09-01')) as $bounced) {
    echo $bounced->messageId, ' ', $bounced->subject, "\n";
}
```

## Streams

Every message goes on a stream of its server. A server starts with a
default transactional stream, and the account adds more: transactional
and broadcast ones, and one inbound stream, which receives mail at its
`inboundAddress` instead of sending. Each sending stream has a
suppression list of its own.

| Call                                                                 | Returns                                                                                                            |
| -------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| `$sendora->streams->create(kind:, name:)`                            | `Stream`; a taken name is `stream_exists`, a second inbound stream `inbound_stream_exists`, both with `existingId` |
| `$sendora->streams->list()`                                          | `StreamList` with `streams`, archived ones included                                                                |
| `$sendora->streams->get($streamId)`                                  | One stream                                                                                                         |
| `$sendora->streams->update($streamId, name:, contentRetentionDays:)` | The updated stream; the days apply to an inbound stream                                                            |
| `$sendora->streams->archive($streamId)`                              | The stream with `archivedAt`; it takes no new messages from then on                                                |

`kind` takes a `StreamKind` or its value. `contentRetentionDays: null`
puts an inbound stream back to the default of 30 days, while leaving the
argument out leaves the days as they are.

```php
use Sendora\Types\StreamKind;

$stream = $sendora->streams->create(kind: StreamKind::Transactional, name: 'Aviseringar');
$sendora->email->send(
    from: 'no-reply@example.se',
    to: ['anna@example.com'],
    subject: 'Leveransen är på väg',
    text: 'Hej Anna, paketet lämnade lagret i dag.',
    streamId: $stream->streamId,
);
```

## Broadcasts

One message to many on a broadcast stream, sent as a whole: the content
once and one entry per message, up to 50,000 and 50 MB per broadcast.
Every message is a message in the log with its own events and webhooks,
carrying the `broadcastId`. Every recipient gets their own unsubscribe
link where `{{ unsubscribe_url }}` stands. Addresses on the stream's
suppression list are dropped and counted in `suppressed`. The SDK sets an
idempotency key, so a retry returns the same broadcast.

A message's `substitutions`, up to 20 strings, replace `{{ key }}` in
the subject, the text and the HTML, escaped in the HTML. A key that the
content names and a message lacks throws `substitution_missing`.

| Call                                         | Returns                                                                  |
| -------------------------------------------- | ------------------------------------------------------------------------ |
| `$sendora->broadcasts->send(...$broadcast)`  | `AcceptedBroadcast` with `broadcastId`, `total`, `suppressed` and `test` |
| `$sendora->broadcasts->get($broadcastId)`    | `Broadcast` with `status`, `released` and `failed`                       |
| `$sendora->broadcasts->list(limit:, after:)` | `BroadcastPage` with `broadcasts` and `next`, newest first               |
| `$sendora->broadcasts->cancel($broadcastId)` | The cancelled broadcast; what had not left counts as failed              |

```php
$accepted = $sendora->broadcasts->send(
    streamId: '7c9e6679-7425-40de-944b-e07fc1f90ae7',
    from: ['email' => 'nyheter@example.se', 'name' => 'Example AB'],
    subject: 'Nyheter i oktober, {{ first_name }}',
    messages: [
        ['to' => ['anna@example.com'], 'substitutions' => ['first_name' => 'Anna']],
        ['to' => ['bo@example.com'], 'substitutions' => ['first_name' => 'Bo']],
    ],
    text: 'Hej! Här är månadens nyheter. Vill du inte ha fler? {{ unsubscribe_url }}',
);
$progress = $sendora->broadcasts->get($accepted->broadcastId);
```

## Suppressions

Addresses the server no longer sends to: hard bounces, spam complaints,
unsubscribes and entries added by hand. A send to one of them throws
`recipient_suppressed`.

| Call                                                      | Returns                                          |
| --------------------------------------------------------- | ------------------------------------------------ |
| `$sendora->suppressions->list(streamId:, limit:, after:)` | `SuppressionPage` with `suppressions` and `next` |
| `$sendora->suppressions->listAll(streamId:)`              | Every entry, as a generator                      |
| `$sendora->suppressions->delete(address:, streamId:)`     | Nothing; the address may be sent to again        |

Every stream has a list of its own. Without `streamId`, the calls mean
the default transactional stream. A spam complaint or a recipient's own
unsubscribe cannot be lifted this way (`spam_complaint_locked`,
`unsubscribe_locked`). Only Sendora support can, at the recipient's own
request. A refused send carries `streamId` beside `suppressed`.

```php
$sendora->suppressions->delete(address: 'anna@example.com');
```

## Tokens

The server's own keys, from its own key. Every key of a server has the
same rights, and its value is shown once, when it is created. A server
holds up to two live keys, and a third is `token_limit`. The account key
manages the same keys under `$account->servers->tokens`.

| Call                                 | Returns                                              |
| ------------------------------------ | ---------------------------------------------------- |
| `$sendora->tokens->create(name:)`    | `CreatedToken` with its `token` value, once          |
| `$sendora->tokens->list()`           | `TokenList` with `tokens`, revoked ones included     |
| `$sendora->tokens->get($tokenId)`    | One token, without its value                         |
| `$sendora->tokens->revoke($tokenId)` | Nothing; the last live key is refused (`last_token`) |

A key's value is kept out of `var_dump()`, `print_r()` and `json_encode()`
of the answer, so a logged answer does not show it. Read it from
`->token`. `toArray()`, `var_export()` and Symfony's `dump()`, which
Laravel's `dd()` uses, still show it. A webhook's secret is kept the same
way.

## The server

The key's own server: its name and id, and whether it is live or a test
server. It lists every limit a send on it is held to, with how much of
each this minute and this month have used. It also lists the account's
sending domains a From address may use.

`inboundCap` is the account's monthly cap on received mail, apart from
the limits on sending, with its `limit`, `used` and `resetsAt`. Past it,
mail to the account is refused for now, so senders retry. It is `null` on
a test server, which receives no mail.

| Call                      | Returns                                                                                                             |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| `$sendora->server->get()` | `OwnServer` with `mode`, `limits`, each with `used` and `resetsAt`, `inboundCap`, and `sendingDomains` to send from |

```php
$own = $sendora->server->get();
foreach ($own->limits as $limit) {
    echo $limit->scope, ' ', $limit->period, ' ', $limit->used, ' of ', $limit->limit, "\n";
}
foreach ($own->sendingDomains as $sending) {
    echo $sending->domain, "\n";
}
```

## Webhooks

An https URL of yours that Sendora posts events to as they happen, signed
with a secret shown once at creation. A webhook holds up to two live
secrets, so a new one can be rolled in beside the old one. Every delivery
is then signed with each, the newest first, and the old secret is deleted
once your receiver has switched.

| Call                                                                    | Returns                                       |
| ----------------------------------------------------------------------- | --------------------------------------------- |
| `$sendora->webhooks->create(url:, events:, streamId:, inboundContent:)` | `CreatedWebhook` with its `secret`, once      |
| `$sendora->webhooks->list()`                                            | `WebhookList` with `webhooks`                 |
| `$sendora->webhooks->get($webhookId)`                                   | One webhook                                   |
| `$sendora->webhooks->delete($webhookId)`                                | Nothing; pending deliveries are dropped       |
| `$sendora->webhooks->deliveries($webhookId, status:, limit:, after:)`   | `DeliveryPage` with `deliveries` and `next`   |
| `$sendora->webhooks->deliveriesAll($webhookId, status:)`                | Every delivery, as a generator                |
| `$sendora->webhooks->replay($webhookId, $deliveryId)`                   | The delivery, queued again                    |
| `$sendora->webhooks->createSecret($webhookId)`                          | A second secret with its `secret` value, once |
| `$sendora->webhooks->deleteSecret($webhookId, $secretId)`               | Nothing; the last live secret is refused      |

Events about a message are `delivered`, `bounced`, `deferred`,
`spam_complaint` and `unsubscribed`. Each has `messageId`, `streamId`,
`recipient`, `occurredAt`, `serverId`, your `tag` and `metadata`, and
`details` from the receiver, or for `unsubscribed` the `source` (`link`
or `one_click`). `cap_warning` and `cap_reached` are about usage, with
`scope`, `cap`, `used` and the period.

`inbound` is about a message received on an inbound stream. It has
`inboundMessageId`, `envelopeRecipient`, the sizes and the
`authentication` verdicts. For a webhook with `inboundContent: 'full'`,
the default, it has the message itself under `content`: `from`, `to`,
`subject`, `text`, `html`, `attachments` and the rest.
`inbound_cap_warning` and `inbound_cap_reached` say that received mail
nears or reaches the account's monthly cap, with `cap`, `used` and the
period. Every event carries `id`, the delivery id, and `attempt`.

A failed delivery is retried with growing delays for about a day, then it
is `dead` until you replay it. Deliveries arrive at least once and not
always in order, so key your handling on `$event->id`.

### Receiving

Verify the signature before you parse anything, answer 2xx quickly, and
do the work afterwards. Under PHP-FPM, `fastcgi_finish_request()` sends
the answer and lets the work go on; elsewhere, hand the work to a queued
job. `WebhookVerifier::verify()` takes the raw body exactly as it arrived
and the `Sendora-Signature` header. It returns the event as a class of
its own.

- The raw body is `file_get_contents('php://input')` in plain PHP and
  `$request->getContent()` in Symfony and Laravel.
- The header is `$_SERVER['HTTP_SENDORA_SIGNATURE']` in plain PHP and
  `$request->headers->get('Sendora-Signature')` in Symfony and Laravel.
- An `inbound` event with its content can take a few megabytes, more than
  nginx's default `client_max_body_size` of 1 MB. Raise it for the
  webhook's route.

```php
use Sendora\Events\BouncedEvent;
use Sendora\Events\InboundEvent;
use Sendora\Events\UnknownEvent;
use Sendora\Exception\WebhookVerificationException;
use Sendora\WebhookVerifier;

$signature = $_SERVER['HTTP_SENDORA_SIGNATURE'] ?? null;

try {
    $event = WebhookVerifier::verify(
        (string) file_get_contents('php://input'),
        signature: is_string($signature) ? $signature : null,
        secret: (string) getenv('SENDORA_WEBHOOK_SECRET'),
    );
} catch (WebhookVerificationException) {
    http_response_code(400);
    exit;
}

if ($event instanceof BouncedEvent && $event->details->hard) {
    error_log("{$event->messageId} bounced for good: {$event->details->classification}");
} elseif ($event instanceof InboundEvent && $event->content !== null) {
    error_log("{$event->inboundMessageId} arrived with {$event->attachmentCount} attachments");
} elseif ($event instanceof UnknownEvent) {
    error_log("An event newer than this release: {$event->event}");
}
http_response_code(204);
```

Tell the events apart with `instanceof`, as above. Comparing
`$event->event` with a string does not narrow the type. Each class is in
`Sendora\Events` and extends `WebhookEvent`, which has `id`, `attempt`
and `event`.

| `event`               | Class                    |
| --------------------- | ------------------------ |
| `delivered`           | `DeliveredEvent`         |
| `bounced`             | `BouncedEvent`           |
| `deferred`            | `DeferredEvent`          |
| `spam_complaint`      | `SpamComplaintEvent`     |
| `unsubscribed`        | `UnsubscribedEvent`      |
| `cap_warning`         | `CapWarningEvent`        |
| `cap_reached`         | `CapReachedEvent`        |
| `inbound`             | `InboundEvent`           |
| `inbound_cap_warning` | `InboundCapWarningEvent` |
| `inbound_cap_reached` | `InboundCapReachedEvent` |
| any other             | `UnknownEvent`           |

A signed event this release cannot read, one newer than it, is an
`UnknownEvent` with its payload in `data`, rather than an error. So your
endpoint can still answer it with 2xx, rather than fail it until Sendora
gives up. Upgrade to read it as its class.

The signature is `Sendora-Signature: t=<unix seconds>,v1=<hex>[,v1=<hex>]`,
one `v1` per live secret of the webhook. Each is an HMAC-SHA256 with that
secret over `<t>.<raw body>`, compared in constant time, and the one your
secret produces is enough. To roll a secret, create the new one with
`webhooks->createSecret()`, and move your receiver to it while both sign
every delivery. Then delete the old one. A signature older than five
minutes is refused (`stale_signature`), and `tolerance:` changes the
limit. A wrong one throws `invalid_signature`, and a missing or empty
secret throws `InvalidArgumentException`.

## The account: servers, keys, domains and erasures

An account key (`ak_…`) manages the account and never sends. An
administrator creates one in the dashboard under Account › API keys, and
the account holds up to two live ones. `SendoraAccount` takes the same
arguments as `Sendora`.

```php
use Sendora\Sendora;
use Sendora\SendoraAccount;

$account = new SendoraAccount(); // the key from SENDORA_ACCOUNT_TOKEN

$server = $account->servers->create(name: 'Fakturering');
$sendora = new Sendora($server->token->token);
```

A server is created with its default transactional stream and its first
key, whose value is in the answer this once. A server holds up to two
live keys and never falls below one. So to rotate a key, create the new
one, switch to it, and revoke the old one.

| Call                                                     | Returns                                                                              |
| -------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `$account->servers->create(name:, mode:)`                | `CreatedServer` with its first key as `token`, once; a taken name is `server_exists` |
| `$account->servers->list()`                              | `ServerList` with `servers`, oldest first                                            |
| `$account->servers->get($serverId)`                      | One server                                                                           |
| `$account->servers->update($serverId, name:)`            | The renamed server                                                                   |
| `$account->servers->delete($serverId)`                   | Nothing; `server_in_flight` while its messages are still being delivered             |
| `$account->servers->tokens->create($serverId, name:)`    | `CreatedToken` with its `token` value, once; a third live key is `token_limit`       |
| `$account->servers->tokens->list($serverId)`             | `TokenList` with `tokens`, revoked ones included                                     |
| `$account->servers->tokens->get($serverId, $tokenId)`    | One key, without its value                                                           |
| `$account->servers->tokens->revoke($serverId, $tokenId)` | Nothing; the last live key is refused (`last_token`)                                 |

Sending domains belong to the account, and every server sends from them.
A domain sends once both DNS records are seen. A lookup that fails at the
resolver (`dns_error`) changes nothing. A verified domain whose record
goes missing keeps sending for 24 hours while the account's
administrators are told, and `failingSince` says when that began.

| Call                                   | Returns                                                                      |
| -------------------------------------- | ---------------------------------------------------------------------------- |
| `$account->domains->create(domain:)`   | `SendingDomain` with `returnPath` (CNAME) and `dkim` (TXT) to add            |
| `$account->domains->list()`            | `DomainList` with `domains`                                                  |
| `$account->domains->get($domainId)`    | One domain with the state of its records                                     |
| `$account->domains->verify($domainId)` | The domain plus `check` per record: `ok`, `missing`, `mismatch`, `dns_error` |
| `$account->domains->delete($domainId)` | Nothing; mail from it is refused from then on                                |

```php
$domain = $account->domains->create(domain: 'example.se');
echo $domain->returnPath->type, ' ', $domain->returnPath->host, ' ', $domain->returnPath->value, "\n";
echo $domain->dkim->type, ' ', $domain->dkim->host, ' ', $domain->dkim->value, "\n";
```

An erasure removes one person's data from every server of the account,
as a request under the right to erasure asks. The content of every
message to or from the address, sent and received, is deleted, and the
address becomes a pseudonym in the message log. The erasure is
`pending`, and Sendora carries it out within a minute. Erasing an address
again changes nothing more, and later mail to or from it is sent and
received as before.

| Call                                   | Returns                                                                                   |
| -------------------------------------- | ----------------------------------------------------------------------------------------- |
| `$account->erasures->create(address:)` | `Erasure`, `pending`                                                                      |
| `$account->erasures->get($erasureId)`  | The erasure; once `done`, its `pseudonym`, `messages` and `receivedMessages`, for 30 days |

```php
$erasure = $account->erasures->create(address: 'anna@example.com');
echo $account->erasures->get($erasure->erasureId)->status, "\n";
```

A key of the wrong kind throws `wrong_token_kind`: a server key on the
account's routes, or an account key on a server's.

## Errors

Every failed call throws a `SendoraException`, which extends
`RuntimeException`. Its class says what happened and its `errorCode` says
why. Every class is in `Sendora\Exception`.

| Class                          | When                                                                  |
| ------------------------------ | --------------------------------------------------------------------- |
| `ApiConnectionException`       | No answer: `connection_failed`                                        |
| `ApiTimeoutException`          | An attempt took longer than `timeout`: `timeout`                      |
| `BadRequestException`          | 400                                                                   |
| `AuthenticationException`      | 401                                                                   |
| `PermissionDeniedException`    | 403                                                                   |
| `NotFoundException`            | 404                                                                   |
| `ConflictException`            | 409                                                                   |
| `UnprocessableEntityException` | 422                                                                   |
| `RateLimitException`           | 429, `rate_limited` and `monthly_cap_reached` alike                   |
| `InternalServerException`      | 500 or more                                                           |
| `ApiStatusException`           | Any other status, and the base of the classes for a status            |
| `ResponseValidationException`  | An answer the SDK could not read; the call took effect                |
| `WebhookVerificationException` | `WebhookVerifier::verify()`: `invalid_signature` or `stale_signature` |

`ApiTimeoutException` extends `ApiConnectionException`. `getCode()`
returns the HTTP status, or 0 when no answer came, and `getMessage()`
the API's message.

A bad argument throws the SDK's `InvalidArgumentException` before any
request: a key with a character no key has, a string that is not UTF-8,
or a path id that is empty, `.` or `..`. It extends PHP's own, and is no
`SendoraException`, since nothing was sent. Both implement
`ExceptionInterface`, so `catch (ExceptionInterface $error)` catches
everything the SDK throws.

```php
use Sendora\Exception\RateLimitException;
use Sendora\Exception\SendoraException;
use Sendora\Types\ErrorCode;

try {
    $sendora->email->send(...$message);
} catch (RateLimitException $error) {
    if ($error->resetsAt !== null) {
        echo 'The monthly cap is used up until ', $error->resetsAt->format('Y-m-d H:i'), " UTC\n";
    } else {
        echo 'Try again in ', $error->retryAfter ?? 60, " seconds\n";
    }
} catch (SendoraException $error) {
    if ($error->errorCode !== ErrorCode::RecipientSuppressed->value) {
        throw $error;
    }
    foreach ($error->suppressed as $entry) {
        echo $entry->address, ' ', $entry->reason, "\n";
    }
}
```

| Code                              | Status | Meaning                                                                                              |
| --------------------------------- | ------ | ---------------------------------------------------------------------------------------------------- |
| `invalid_request`                 | 400    | A field is wrong; `issues` says which.                                                               |
| `idempotency_key_required`        | 400    | A batch was sent without a key, which the SDK always sends.                                          |
| `unauthorized`                    | 401    | The key is missing, malformed or revoked.                                                            |
| `wrong_token_kind`                | 403    | A server key where an account key is needed, or the reverse; `message` names the kind.               |
| `payment_required`                | 402    | The account's first payment has not arrived, or an unpaid charge has stopped live sending.           |
| `account_paused`                  | 403    | Sendora has paused the account.                                                                      |
| `account_not_active`              | 403    | The account is not approved to send, or not active for an inbound stream.                            |
| `spam_complaint_locked`           | 403    | Only support can lift a spam-complaint suppression.                                                  |
| `unsubscribe_locked`              | 403    | Only support can lift a recipient's own unsubscribe.                                                 |
| `not_found`                       | 404    | No such id on this server, or no such suppressed address.                                            |
| `domain_exists`                   | 409    | The account already has the domain; `existingId` names it.                                           |
| `domain_reserved`                 | 400    | The name is Sendora's own; no account can receive on it.                                             |
| `inbound_domain_exists`           | 409    | The stream already has a domain (`existingId` names it), or another account holds the name verified. |
| `webhook_exists`                  | 409    | The server already has a webhook for that URL; `existingId` names it.                                |
| `stream_exists`                   | 409    | The server already has a stream with that name; `existingId` names it.                               |
| `inbound_stream_exists`           | 409    | The server already has a live inbound stream; `existingId` names it.                                 |
| `default_stream`                  | 409    | The default stream cannot be archived.                                                               |
| `last_token`                      | 409    | The only live key cannot be revoked.                                                                 |
| `last_secret`                     | 409    | The only live secret of a webhook cannot be deleted.                                                 |
| `secret_limit`                    | 409    | The webhook already holds two live secrets; `max` says how many.                                     |
| `token_limit`                     | 409    | The server or the account already holds two live keys; `max` says how many.                          |
| `server_exists`                   | 409    | The account already has a server with that name.                                                     |
| `server_limit`                    | 409    | The account has as many servers as it may; `max` says how many.                                      |
| `server_reserved`                 | 409    | The server sends Sendora's own sign-in mail and cannot be renamed or removed.                        |
| `server_in_flight`                | 409    | Messages of the server are still being delivered; delete it later.                                   |
| `request_too_large`               | 413    | The body exceeds 10 MB, or 64 MB for a broadcast.                                                    |
| `from_domain_not_verified`        | 422    | The From domain is not a verified sending domain.                                                    |
| `stream_not_found`                | 422    | `streamId` names no stream of this server.                                                           |
| `stream_archived`                 | 422    | The stream is archived and takes no new messages.                                                    |
| `stream_paused`                   | 422    | Sendora paused the stream after complaints; support resumes it.                                      |
| `stream_not_sendable`             | 422    | An inbound stream receives mail; it takes no messages and has no list.                               |
| `stream_not_broadcast`            | 422    | A broadcast goes on a broadcast stream; this one is transactional.                                   |
| `substitution_missing`            | 422    | The content names a `{{ key }}` a message does not give; `index` and `keys` say which.               |
| `broadcast_not_open`              | 409    | The broadcast is completed or cancelled; nothing is left to cancel.                                  |
| `unsubscribe_placeholder_missing` | 422    | A broadcast message lacks `{{ unsubscribe_url }}` in a part.                                         |
| `list_unsubscribe_reserved`       | 422    | Sendora writes List-Unsubscribe on broadcast streams; leave it out.                                  |
| `recipient_suppressed`            | 422    | Recipients on the suppression list; `suppressed` lists them.                                         |
| `test_address_on_live_server`     | 422    | Addresses at simulator.sendora.se are for test servers; `addresses` lists them.                      |
| `stream_kind_not_allowed`         | 422    | A test server has no inbound stream; receive on a live server.                                       |
| `mode_immutable`                  | 400    | A server's mode is fixed at creation; create a new server for the other mode.                        |
| `idempotency_key_mismatch`        | 422    | The key was used before with a different body.                                                       |
| `content_expired`                 | 410    | The received message's content window has passed; the message itself stays.                          |
| `content_unreadable`              | 409    | The received message's stored content cannot be opened; Sendora has been told.                       |
| `rate_limited`                    | 429    | Per-minute limit; `retryAfter`, `scope`, `limit`. From the API's proxy, too many requests at once.   |
| `monthly_cap_reached`             | 429    | The monthly cap is used up; `scope`, `cap`, `used`, `resetsAt`.                                      |
| `sending_disabled`                | 503    | Sending is paused for everyone; `retryAfter`.                                                        |
| `connection_failed`               | none   | The request did not reach the API, or its answer was lost; `getMessage()` says why.                  |
| `timeout`                         | none   | An attempt took longer than `timeout`.                                                               |
| `unexpected_response`             | any    | An answer without a JSON error body, such as from a proxy.                                           |
| `invalid_signature`               | none   | A webhook's signature is missing, malformed or wrong.                                                |
| `stale_signature`                 | none   | A webhook's signature is older than the tolerance.                                                   |

`$error->isRetryable()` is true when waiting and calling again could
succeed. `ErrorCode` lists every code this release knows. A code newer
than the release comes through as its string, its class chosen by the
status.

Neither the key nor a request's subject, text, HTML or attachments is
ever part of an exception, its message or a dump of it. The recipient
addresses the API names are in `suppressed` and `addresses` alone.

## New values and fields

The API may add a value to a set the SDK knows, such as a recipient's
status, before your release does. So such a property is a `string`,
which takes the new value rather than failing the call. A backed enum,
such as `RecipientStatus`, lists the values the release knows. Compare
with `RecipientStatus::Delivered->value`, or use `tryFrom()`. A `match`
over such a property keeps a `default` arm.

A field newer than your release is not a property yet, but every answer
keeps what the API sent. `$answer->toArray()` returns it as arrays, with
the API's camelCase keys, in which an empty object becomes an empty
array. `json_encode($answer)` writes the JSON back as it came.

## Retries and timeouts

Lost connections, timeouts, `rate_limited`, `sending_disabled` and server
errors are retried with jittered backoff, twice by default. That includes
the plain-text 429 of the proxy in front of the API. A retry honours
`retryAfter`, the seconds the API asks for, when it fits within five
seconds of waiting in all. A longer `retryAfter` is thrown at once for
you to schedule. No other failure is retried, so `monthly_cap_reached`
is thrown at once, with `resetsAt`.

A call that creates something is repeated only after a `rate_limited` or
`sending_disabled` answer. It is never repeated after a lost connection
or a server error, so nothing is created twice. These calls are
`streams->create()`, `tokens->create()`, `webhooks->create()`,
`webhooks->createSecret()`, `webhooks->replay()`,
`inboundDomains->create()`, `servers->create()`,
`servers->tokens->create()` and `domains->create()`.

`timeout` is in seconds, 30 by default, and bounds a whole attempt,
connecting included. `maxRetries: 0` turns retries off. `withOptions()`
returns a copy of the client that shares its connection, for one call
that may take longer or a job that must not retry:

```php
use Sendora\Sendora;

$sendora = new Sendora(timeout: 10, maxRetries: 3);
$patient = $sendora->withOptions(timeout: 120, maxRetries: 0);
```

The SDK waits in the process between attempts, so a retry holds a web
request up to five seconds longer. PHP has no standard event loop, so
there is no async client. When a request must not wait, send from a
queued job, such as a Symfony Messenger handler or a Laravel job.

## Symfony Mailer

`SendoraTransport` sends Symfony Mailer's messages through the API rather
than over SMTP. Its factory takes the DSN `sendora+api://KEY@default`,
with the server key as the user. Install `symfony/mailer`, from 6.4 to
8.x, beside the SDK.

In a Symfony app, register the factory as a service, and set the DSN:

```yaml
# config/services.yaml
services:
    Sendora\Mailer\SendoraTransportFactory:
        parent: mailer.transport_factory.abstract
        tags: ['mailer.transport_factory']
```

```dotenv
MAILER_DSN=sendora+api://sk_…@default
```

Symfony's own `config/packages/mailer.yaml` reads `MAILER_DSN`, so the
app's `MailerInterface` then sends through Sendora. `?stream=<stream id>`
sends on a stream other than the default. Any host but `default` is used
as given, over https. Without the framework, give the transport a
client, or build the DSN's transport from the factory:

```php
use Sendora\Mailer\SendoraTransport;
use Sendora\Mailer\SendoraTransportFactory;
use Sendora\Sendora;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

$mailer = new Mailer(new SendoraTransport(new Sendora()));

// Or from the DSN, beside Symfony's own transports:
$factories = [new SendoraTransportFactory(), ...Transport::getDefaultFactories()];
$mailer = new Mailer((new Transport($factories))->fromString((string) getenv('MAILER_DSN')));

$email = (new Email())
    ->from('no-reply@example.se')
    ->to('anna@example.com')
    ->subject('Din faktura för september')
    ->text('Hej Anna, fakturan finns bifogad.')
    ->attachFromPath('faktura.pdf', contentType: 'application/pdf');
$email->getHeaders()
    ->add(new TagHeader('invoice'))
    ->add(new MetadataHeader('invoice_id', '2026-0912'));
$mailer->send($email);
```

`Transport::fromDsn()` alone knows only Symfony's own transports. A
message maps to a send this way:

| From the message                                                                            | To the send                                       |
| ------------------------------------------------------------------------------------------- | ------------------------------------------------- |
| The From header's address and name                                                          | `from`; a second address fails the send           |
| The envelope's recipients, each placed by the To, Cc and Bcc headers                        | `to`, `cc`, `bcc`                                 |
| The subject, and the text and HTML bodies                                                   | `subject`, `text`, `html`                         |
| Attachments, and inline parts with their content id                                         | `attachments`                                     |
| `Reply-To`, `In-Reply-To`, `References`, the two unsubscribe headers and other `X-` headers | `headers`, the first of each name                 |
| `X-Sendora-Tag`, or else `TagHeader`                                                        | `tag`; two different tags fail the send           |
| `MetadataHeader` and `X-Sendora-Metadata-<key>`                                             | `metadata`, `X-Sendora-Metadata-` winning a tie   |
| `X-Sendora-Stream`, or else the DSN's `stream`                                              | `streamId`                                        |
| `X-Sendora-Idempotency-Key`                                                                 | `idempotencyKey`, or else a new key for each send |

- Any other header, such as `Importance`, is left off, as over SMTP. The
  `X-Sendora-`, `X-Tag` and `X-Metadata-` headers never reach `headers`.
- The envelope decides who gets the message, as over SMTP. A recipient
  that a listener adds, such as Symfony's `EnvelopeListener` redirecting
  a staging app's mail, goes to `to`. A Cc or Bcc address that the
  envelope lacks gets nothing.
- After a send, the sent message's `getMessageId()` is Sendora's message
  id, the one the log and the webhooks use. Symfony's `SentMessageEvent`
  carries it.
- A failure is a `TransportException` with the SDK's message, the HTTP
  status as its code and the SDK's exception as its previous. So
  Symfony's failover and round-robin transports work.
- Set `X-Sendora-Idempotency-Key` from an id of your own, such as an
  order's, when a queued mail may be sent again. It helps only when the
  message renders the same each time. An embedded image gets a new
  content id on each render, and the API then refuses the key with
  `idempotency_key_mismatch`. The transport's message then says that the
  mail went out on an earlier attempt.

To send over SMTP instead, Symfony's own `smtp://` transport reaches
Sendora, as the SMTP guide at [sendora.se/docs](https://sendora.se/docs)
shows.

## Your own HTTP client

Without a client of your own, the SDK speaks to the API through curl. It
takes a proxy from `https_proxy`, `HTTPS_PROXY`, `all_proxy` or
`ALL_PROXY`, and honours `NO_PROXY`, so a proxy needs no setting in the
SDK. PHP-FPM passes those variables to its workers only when the pool
sets them with `env[HTTPS_PROXY]` or `clear_env = no`.

Certificates are verified against `curl.cainfo` or `openssl.cafile` in
`php.ini`, or curl's default store. A file set there takes the place of
curl's store, so it must hold the public roots as well as your own
authority, such as the one a TLS-inspecting proxy signs with. On Windows,
PHP ships no certificate file, so when neither setting is set, the SDK
uses Windows' own store. A container without a store needs its
`ca-certificates` package.

Pass any PSR-18 client as `httpClient:` for a private certificate
authority, tracing or tests. The SDK still builds every request, with its
own headers, and still retries. `requestFactory:` and `streamFactory:`
build the requests, and default to the client itself when it implements
PSR-17, as Symfony's `Psr18Client` does. Guzzle's client needs both, such
as `new GuzzleHttp\Psr7\HttpFactory()`. A client of your own uses its own
proxy and certificate settings.

- Set a timeout on the client. PSR-18 has none, so the SDK's `timeout`
  does not reach it.
- Turn redirects off. The API never redirects.
- Pass a client without default headers. The SDK's own headers win, but
  the client's others still go out.

```php
use Sendora\Sendora;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

$http = HttpClient::create([
    'max_duration' => 30,
    'max_redirects' => 0,
    'cafile' => '/etc/ssl/certs/internal-ca.pem',
]);
$sendora = new Sendora(httpClient: new Psr18Client($http));
```

Symfony's `Psr18Client` needs a PSR-7 implementation, such as
`nyholm/psr7`. A failure of the client's is `connection_failed`, and its
class and message are in the exception's message.

## Testing your code

For tests that talk to Sendora, create a test server. It goes through
everything a live one does: validation, the suppression list, the log
and the webhooks. But nothing it accepts is delivered. Its keys start
`sk_test_`, and `Keys::isTestKey()` tells them apart, so a production
process can refuse one at startup.

Addresses at `simulator.sendora.se` (`hardbounce@`, `softbounce@`,
`deferred@`, `complaint@`, with any `+label`) produce those outcomes,
and any other address is marked delivered. A server's mode is fixed
when it is created, so going live means a new server and a new key.
Test mail is free and counts against its own monthly cap, shared by the
account's test servers.

```php
use Sendora\Keys;
use Sendora\Sendora;
use Sendora\SendoraAccount;

$account = new SendoraAccount();
$ci = $account->servers->create(name: 'CI', mode: 'test');

$token = (string) getenv('SENDORA_API_TOKEN');
if (getenv('APP_ENV') === 'production' && Keys::isTestKey($token)) {
    throw new RuntimeException('SENDORA_API_TOKEN is a test key, and nothing would be delivered.');
}
$sendora = new Sendora($token);
```

For unit tests that must not reach the network, give the client a fake
PSR-18 client that returns what the API would, such as Symfony's
`MockHttpClient` inside a `Psr18Client`:

```php
use PHPUnit\Framework\TestCase;
use Sendora\Sendora;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class InvoiceMailTest extends TestCase
{
    public function testTheInvoiceIsSent(): void
    {
        $answer = new JsonMockResponse([
            'messageId' => '0b7c6a55-6f0e-4bd4-9a3c-0f6f2f8d4a11',
            'status' => 'accepted',
            'submittedAt' => '2026-09-26T10:00:00.000Z',
            'test' => false,
        ]);
        $sendora = new Sendora('sk_unit_test', httpClient: new Psr18Client(new MockHttpClient($answer)));

        $accepted = $sendora->email->send(from: 'no-reply@example.se', to: ['anna@example.com'], subject: 'Hej', text: 'Hej');

        self::assertSame('https://api.sendora.se/v1/email', $answer->getRequestUrl());
        self::assertSame('accepted', $accepted->status);
    }
}
```

To test a failure, answer with the API's error body and status, such as
`422` with `{"error": "from_domain_not_verified", "message": "…"}`. The SDK
repeats a call after a retryable answer, such as a `503`, so pass
`maxRetries: 0` in a test that gives one.

Code that takes a resource, such as `Email`, rather than the client can
take a double of it. Each resource class can be doubled with PHPUnit's
`createMock()` or with Mockery. An answer's or a webhook event's
constructor is outside the semver promise, since a new property adds a
parameter. So build either with named arguments, as below, and expect an
upgrade to ask for more.

```php
use PHPUnit\Framework\TestCase;
use Sendora\Resources\Email;
use Sendora\Types\AcceptedEmail;

final class WelcomeMail
{
    public function __construct(private readonly Email $email) {}

    public function send(string $address): string
    {
        $accepted = $this->email->send(from: 'no-reply@example.se', to: [$address], subject: 'Välkommen', text: 'Hej!');

        return $accepted->messageId;
    }
}

final class WelcomeMailTest extends TestCase
{
    public function testTheWelcomeIsSent(): void
    {
        $email = $this->createMock(Email::class);
        $email->expects(self::once())->method('send')->willReturn(new AcceptedEmail(
            messageId: '0b7c6a55-6f0e-4bd4-9a3c-0f6f2f8d4a11',
            status: 'accepted',
            submittedAt: new DateTimeImmutable('2026-09-26T10:00:00Z'),
            test: true,
        ));

        self::assertSame('0b7c6a55-6f0e-4bd4-9a3c-0f6f2f8d4a11', (new WelcomeMail($email))->send('anna@example.com'));
    }
}
```

## Logging and tracing

The SDK logs one thing: each retry, at debug level, on the PSR-3 logger
you pass as `logger:`. The entry has the method, the path, the code and
the wait. Monolog and the loggers of Symfony and Laravel all take PSR-3.

A stack trace never shows the key, or a message's addresses, subject,
text, HTML or attachments. The SDK marks those parameters
`#[\SensitiveParameter]`, so PHP puts a placeholder in their place. That
holds in an error tracker's traces and in Laravel's failed jobs alike.

The client sends every attempt through the PSR-18 client you give it,
retries included. So a decorator around it sees each attempt and can
time it:

```php
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Sendora\Sendora;
use Symfony\Component\HttpClient\Psr18Client;

final class TimedClient implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $inner,
        private readonly LoggerInterface $logger,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $started = microtime(true);
        $response = $this->inner->sendRequest($request);
        $this->logger->info('{method} {path} {status} in {seconds} s', [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'status' => $response->getStatusCode(),
            'seconds' => round(microtime(true) - $started, 3),
        ]);

        return $response;
    }
}

$psr18 = new Psr18Client();
$sendora = new Sendora(
    httpClient: new TimedClient($psr18, $logger),
    requestFactory: $psr18,
    streamFactory: $psr18,
);
```

Record the path, never the request's body, which holds the message.

## For coding agents

The package ships `skills/sendora/SKILL.md`, a short guide in the agent
skill format with the rules above. Point your agent at
`vendor/sendora/sendora-php/skills/sendora/SKILL.md` or at this README.
Every public method carries a doc comment, and PHPStan reads the array
shapes in them.

## Versions

The package follows semantic versioning. A breaking change comes only in
a major version: a renamed or removed method, argument or property, or a
changed error code. A minor adds methods, arguments, properties, codes or
events, and a patch fixes a bug. `composer require` writes a constraint
such as `^1.0`, which takes every later 1.x release.

A release adds an argument only at the end of its method, so a call by
position keeps working. An answer's or a webhook event's constructor is
outside the promise.
`CHANGELOG.md` lists what each release changed.

## Docs

Guides and the HTTP reference: [sendora.se/docs](https://sendora.se/docs).
