---
name: sendora
description: Send transactional email through Sendora with the sendora/sendora-php package, read the message log, manage streams, suppressions, keys, webhooks, servers and sending domains, receive signed webhooks, and send Symfony Mailer's mail through the API. Use when a task mentions Sendora, the sendora/sendora-php package, or sending email from a PHP system hosted in Sweden.
---

# Sendora

`sendora/sendora-php` is the PHP client for the Sendora API. It runs on
PHP 8.2 or newer with ext-curl and ext-json, and is typed for PHPStan.
The README of this package, `README.md` at its root, is the complete
reference: every call, every error code and every event is in it.

## Rules

- Two kinds of key, two clients. A server key (`sk_…`) drives
  `new Sendora()`: `email`, `broadcasts`, `messages`, `inbound`,
  `inboundDomains`, `server`, `streams`, `suppressions`, `tokens` and
  `webhooks`, everything inside one server. An account key (`ak_…`)
  drives `new SendoraAccount()`: `servers`, `servers->tokens`, `domains`
  and `erasures`, and it never sends. A key of the wrong kind throws
  `wrong_token_kind`.
- Every key is a secret. Leave `token` out and the clients read
  `SENDORA_API_TOKEN` and `SENDORA_ACCOUNT_TOKEN` from `$_SERVER`,
  `$_ENV` or `getenv()`. Never hard-code a key or send it to a browser.
  Under PHP-FPM, set the variable in the pool with `env[...]` or pass the
  key in. A client without a key throws `InvalidArgumentException`, so
  construct one per process at startup and share it, as a service in a
  container.
- A server is created with its first key:
  `$account->servers->create(name: ...)` returns `->token->token` once,
  and `new Sendora($token)` sends with it. A server or the account holds
  at most two live keys. So rotate by creating the new key, switching to
  it, then revoking the old one. The last live key can never be revoked.
- For tests and CI, use a test server:
  `$account->servers->create(name: ..., mode: 'test')`. It accepts, logs
  and fires webhooks like a live one but delivers nothing. Its keys start
  `sk_test_`, and `Keys::isTestKey($token)` tells them apart at startup.
  Addresses at `simulator.sendora.se` (`hardbounce@`, `softbounce@`,
  `deferred@`, `complaint@`) produce those outcomes on a test server and
  are refused on a live one (`test_address_on_live_server`). A server's
  mode never changes. For unit tests, pass `httpClient:` a fake PSR-18
  client, such as Symfony's `MockHttpClient` inside a `Psr18Client`, or
  double a resource class with `createMock()`.
- Arguments are named, with the API's camelCase names. The ids of a path
  come first, by position, and every other argument is named. Do not
  build request bodies by hand or call curl yourself.
- Do not add idempotency keys or retry loops of your own. The SDK gives
  every send a key and repeats a call only when repeating is safe. Pass
  `idempotencyKey:` only when a retry may come from another process,
  such as a queued job. There is no async client: send from a queued job
  when a request must not wait.
- Every failed call throws a `SendoraException`. Catch its subclass or
  compare `$error->errorCode` with an `ErrorCode` case's `->value`, and
  rethrow anything else. Never swallow it. A bad argument throws the
  SDK's `InvalidArgumentException` before any request.
- Answers are readonly classes. A property that holds one of a set of
  values, such as a recipient's `status`, is a string that may hold a
  value newer than the release. Compare it with an enum's `->value`, such
  as `RecipientStatus::Delivered->value`, and give a `match` over it a
  `default` arm.
- A batch never throws for one bad message: check each of
  `sendBatch(...)->results` with `instanceof RefusedBatchItem`.
- Delivery is asynchronous. `email->send()` returns `accepted`. Whether
  the receiver took the message is in
  `messages->get($messageId)->recipients` and in the `delivered`,
  `bounced`, `deferred` and `spam_complaint` webhook events.
- Verify a webhook before parsing it with `WebhookVerifier::verify()`.
  Give it the raw body exactly as it arrived,
  `file_get_contents('php://input')` or `$request->getContent()`, with
  `signature:` the `Sendora-Signature` header and `secret:` the
  webhook's secret. Tell the events apart with `instanceof`, never by
  comparing `$event->event`, and handle `UnknownEvent`, an event newer
  than the release. Answer 2xx quickly and do the work afterwards.
  Deliveries arrive at least once, so key handling on `$event->id`.
- Addresses on the suppression list are refused with
  `recipient_suppressed`. Lift a hard bounce with
  `suppressions->delete(address: ...)`. A spam complaint or an
  unsubscribe cannot be lifted by the customer. Lists are per stream:
  pass `streamId:` when the send went on a stream other than the default.
- `from` must be on a verified sending domain of the account.
  `$account->domains->create(domain: ...)` returns the two DNS records to
  add, and `$account->domains->verify()` checks them now. A server key
  cannot manage domains, but `server->get()` names those a From may use
  (`sendingDomains`). A verified domain whose record goes missing keeps
  sending for 24 hours (`failingSince`), and a `dns_error` changes
  nothing.
- A person who asks to be forgotten is erased with the account key.
  `$account->erasures->create(address: ...)` returns a pending erasure,
  and `$account->erasures->get($erasureId)` shows it `done` within a
  minute. It deletes their messages' content and puts a pseudonym in the
  log on every server. It blocks nothing.
- A message goes on the server's default transactional stream unless it
  names another with `streamId:`, and `streams->create()` adds one. Never
  make a stream per message or per customer: a stream is a kind of mail.
- Mail to many goes as one broadcast: `broadcasts->send()` on a broadcast
  stream, with the content once and `messages:` holding one entry per
  recipient, at most 50,000. Follow it with `broadcasts->get()` and stop
  it with `broadcasts->cancel()`. Personalise with `{{ key }}` in the
  content and `substitutions` on each entry. Never loop over
  `email->send()` for a list.
- A message on a broadcast stream must carry `{{ unsubscribe_url }}` in
  its text and its HTML. Sendora puts each recipient's own link there and
  writes the List-Unsubscribe headers itself. An `unsubscribed` event
  tells your system to stop, and the address is then on that stream's
  list.
- Symfony Mailer sends through the API with
  `MAILER_DSN=sendora+api://KEY@default`, once `SendoraTransportFactory`
  is registered as the README shows. Symfony's `TagHeader` and
  `MetadataHeader` become the tag and the metadata.
- In a Laravel app, install `sendora/sendora-laravel`, which installs
  this package. `MAIL_MAILER=sendora` sends Laravel's mail, the clients
  come from the container, and the package's route checks each webhook
  and fires a Laravel event. Its README has the settings.

## The shape of a call

```php
use Sendora\Exception\SendoraException;
use Sendora\Sendora;
use Sendora\Types\ErrorCode;

$sendora = new Sendora();

try {
    $accepted = $sendora->email->send(
        from: ['email' => 'no-reply@example.se', 'name' => 'Example AB'],
        to: ['anna@example.com'],
        subject: 'Din faktura för september',
        text: 'Hej Anna, fakturan finns bifogad.',
        tag: 'invoice',
    );
    echo $accepted->messageId, "\n";
} catch (SendoraException $error) {
    if ($error->errorCode !== ErrorCode::RecipientSuppressed->value) {
        throw $error;
    }
    foreach ($error->suppressed as $entry) {
        echo $entry->address, ' is suppressed: ', $entry->reason, "\n";
    }
}
```

## Where to read more

- The README of this package: every method, every error code, retries,
  webhooks and events, the Symfony Mailer transport, testing, logging
  and tracing.
- The guides and the HTTP reference at https://sendora.se/docs.
