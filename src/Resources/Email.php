<?php

declare(strict_types=1);

namespace Sendora\Resources;

use Sendora\Attachment;
use Sendora\Exception\InvalidArgumentException;
use Sendora\Exception\SendoraException;
use Sendora\Internal\Call;
use Sendora\Internal\Caller;
use Sendora\Internal\Idempotency;
use Sendora\Internal\Wire;
use Sendora\Types\AcceptedEmail;
use Sendora\Types\BatchResult;

/**
 * Hands messages over for delivery, one at a time or up to 100 at once.
 *
 * `EmailMessage` is one message as an array, with the keys that `send()`
 * takes as arguments. `send(...$message)` takes it, and so does each item
 * of `sendBatch()`. Import it with
 * `@phpstan-import-type EmailMessage from Email`.
 *
 * @phpstan-type Address string|array{email: string, name?: null|string}
 * @phpstan-type EmailAttachment Attachment|array{name: string, content: string, contentType?: null|string, contentId?: null|string}
 * @phpstan-type EmailMessage array{
 *     from: Address,
 *     to: list<Address>,
 *     subject: string,
 *     streamId?: null|string,
 *     cc?: null|list<Address>,
 *     bcc?: null|list<Address>,
 *     text?: null|string,
 *     html?: null|string,
 *     headers?: null|array<array-key, string>,
 *     attachments?: null|list<EmailAttachment>,
 *     tag?: null|string,
 *     metadata?: null|array<array-key, string>,
 * }
 *
 * @final
 */
class Email
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * Sends one message and answers at once with its id. Delivery,
     * deferral, bounce and complaint come later, as events on the message
     * and as webhooks. Every send goes under an idempotency key, yours or a
     * random one, so a retry never sends twice.
     *
     * At least one of `text` and `html` is needed. The API refuses a
     * message with neither.
     *
     * ```php
     * $sent = $sendora->email->send(
     *     from: ['email' => 'no-reply@example.se', 'name' => 'Example AB'],
     *     to: ['anna@example.com'],
     *     subject: 'Din faktura för september',
     *     text: 'Hej Anna, fakturan finns bifogad.',
     *     attachments: [Attachment::fromFile('/srv/invoices/1042.pdf', contentType: 'application/pdf')],
     *     tag: 'invoice',
     * );
     * echo $sent->messageId;
     * ```
     *
     * @param Address $from the sender, whose domain must be a verified sending domain of the account
     * @param list<Address> $to the recipients; at most 50 across `to`, `cc` and `bcc`
     * @param string $subject the subject line, 1 to 998 characters
     * @param null|string $streamId the stream of this server the message goes on; the default transactional stream when left out
     * @param null|list<Address> $cc copied recipients, shown in the message
     * @param null|list<Address> $bcc blind-copied recipients
     * @param null|string $text the plain-text part
     * @param null|string $html the HTML part
     * @param null|array<array-key, string> $headers custom headers by name, sent as given; a header that Sendora sets itself, such as From or DKIM-Signature, is refused
     * @param null|list<EmailAttachment> $attachments at most 20, and the built message may not exceed 10 MB
     * @param null|string $tag a label of up to 100 characters, returned with the message and its events
     * @param null|array<array-key, string> $metadata up to 20 pairs of your own, returned with the message and its events, their keys sent as given
     * @param null|string $idempotencyKey 1 to 255 characters naming this send, none of them a control character or beyond U+00FF. The API keeps a key for 24 hours. The same key with the same message answers the first send again, and with another message is refused. Give your own to repeat a send safely from another process.
     *
     * @throws SendoraException the send was refused or failed; its `idempotencyKey` is the key it went under, for repeating it later
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function send(
        #[\SensitiveParameter]
        array|string $from,
        #[\SensitiveParameter]
        array $to,
        #[\SensitiveParameter]
        string $subject,
        ?string $streamId = null,
        #[\SensitiveParameter]
        ?array $cc = null,
        #[\SensitiveParameter]
        ?array $bcc = null,
        #[\SensitiveParameter]
        ?string $text = null,
        #[\SensitiveParameter]
        ?string $html = null,
        #[\SensitiveParameter]
        ?array $headers = null,
        #[\SensitiveParameter]
        ?array $attachments = null,
        ?string $tag = null,
        #[\SensitiveParameter]
        ?array $metadata = null,
        ?string $idempotencyKey = null,
    ): AcceptedEmail {
        $key = Idempotency::keyFor($idempotencyKey);
        $body = Wire::body([
            'from' => $from,
            'to' => $to,
            'subject' => $subject,
            'streamId' => $streamId,
            'cc' => $cc,
            'bcc' => $bcc,
            'text' => $text,
            'html' => $html,
            'headers' => $headers,
            'attachments' => $attachments,
            'tag' => $tag,
            'metadata' => $metadata,
        ]);

        return $this->caller->json(
            new Call('POST', '/v1/email', idempotent: true, body: $body, idempotencyKey: $key),
            AcceptedEmail::fromWire(...),
        );
    }

    /**
     * Sends up to 100 messages and answers one result per message, in
     * order. Each message is accepted with its id, or refused with the
     * error a single send would give. The batch as a whole never fails
     * halfway, so read each result. A retry under the same key sends
     * nothing twice: a message accepted before comes back with `replayed`
     * set.
     *
     * ```php
     * $batch = $sendora->email->sendBatch($messages, idempotencyKey: 'invoices-2026-09');
     * foreach ($batch->results as $result) {
     *     if ($result instanceof RefusedBatchItem) {
     *         echo $result->index, ' ', $result->errorCode, "\n";
     *     }
     * }
     * ```
     *
     * @param list<EmailMessage> $messages the messages, each as `send()` takes one
     * @param null|string $idempotencyKey 1 to 255 characters naming this batch, as `send()` takes one; a random one when not given
     *
     * @throws SendoraException the batch as a whole was refused or failed; its `idempotencyKey` is the key it went under
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function sendBatch(#[\SensitiveParameter] array $messages, ?string $idempotencyKey = null): BatchResult
    {
        $key = Idempotency::keyFor($idempotencyKey);

        return $this->caller->json(
            new Call('POST', '/v1/email/batch', idempotent: true, body: Wire::listBody($messages), idempotencyKey: $key),
            BatchResult::fromWire(...),
        );
    }

    /**
     * Shows the API's address alone.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->caller->baseUrl];
    }
}
