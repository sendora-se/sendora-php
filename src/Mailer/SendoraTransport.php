<?php

declare(strict_types=1);

namespace Sendora\Mailer;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Sendora\Exception\ExceptionInterface;
use Sendora\Exception\SendoraException;
use Sendora\Internal\MailerMapping;
use Sendora\Sendora;
use Sendora\Types\ErrorCode;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeException;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * A transport for Symfony's mailer that sends through Sendora's API rather
 * than over SMTP. Symfony apps and Laravel's mail both use it.
 *
 * ```php
 * $mailer = new Mailer(new SendoraTransport(new Sendora()));
 * $mailer->send(
 *     (new Email())
 *         ->from('notices@example.se')
 *         ->to('anna@example.com')
 *         ->subject('Your order')
 *         ->text('Thank you for your order.'),
 * );
 * ```
 *
 * The envelope says who gets each message, and the To, Cc and Bcc headers
 * where each recipient shows. Symfony's `TagHeader`, or `X-Sendora-Tag`,
 * gives the tag, and each `MetadataHeader`, or `X-Sendora-Metadata-<key>`,
 * an entry of the metadata. `X-Sendora-Stream` picks the stream, and
 * `X-Sendora-Idempotency-Key` the send's idempotency key. Reply-To, the
 * thread's and the unsubscribe headers and every other `X-` header pass
 * on. After a send, the sent message's `getMessageId()` is Sendora's id.
 */
final class SendoraTransport extends AbstractTransport
{
    /**
     * @param Sendora $client the client that sends, with a key of the server the mail goes from
     * @param null|string $stream the stream each message goes on, unless its `X-Sendora-Stream` names another; the server's default transactional stream when not given
     * @param null|EventDispatcherInterface $dispatcher fires `MessageEvent` before each send, which Twig's templates and the envelope listener need
     * @param null|LoggerInterface $logger the mailer's logger
     */
    public function __construct(
        private readonly Sendora $client,
        private readonly ?string $stream = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    /** The scheme and the API's host, never the key. */
    public function __toString(): string
    {
        $baseUrl = $this->client->__debugInfo()['baseUrl'];
        $port = \parse_url($baseUrl, \PHP_URL_PORT);

        return 'sendora+api://' . \parse_url($baseUrl, \PHP_URL_HOST) . (\is_int($port) ? ":{$port}" : '');
    }

    /**
     * @throws TransportException the message cannot go as one send, or the API refused or failed it; the SDK's exception is its previous
     */
    protected function doSend(#[\SensitiveParameter] SentMessage $message): void
    {
        $original = $message->getOriginalMessage();
        if (!$original instanceof Message) {
            throw new TransportException('Sendora takes an Email or a Message, whose parts it can read, and this message is raw.');
        }
        try {
            $email = MessageConverter::toEmail($original);
        } catch (MimeException $error) {
            throw new TransportException("Sendora cannot read this message as an email: {$error->getMessage()}", 0, $error);
        }
        $arguments = MailerMapping::arguments($email, $message->getEnvelope(), $this->stream);
        try {
            $accepted = $this->client->email->send(...$arguments);
        } catch (ExceptionInterface $error) {
            throw new TransportException(self::reasonFor($error), $error->getCode(), $error);
        }
        $message->setMessageId($accepted->messageId);
    }

    /**
     * The SDK's message, but for a key reused with another message: the
     * earlier attempt was accepted, which a retry that renders anew can
     * meet, as an embedded image gets a new content id each time.
     */
    private static function reasonFor(ExceptionInterface $error): string
    {
        if ($error instanceof SendoraException && $error->errorCode === ErrorCode::IdempotencyKeyMismatch->value) {
            return 'This mail went out on an earlier attempt under the same X-Sendora-Idempotency-Key, so Sendora refused this attempt, whose message differs from it.';
        }

        return $error->getMessage();
    }
}
