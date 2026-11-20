<?php

declare(strict_types=1);

namespace Sendora\Mailer\Webhook;

use Sendora\Events\BouncedEvent;
use Sendora\Events\ClickedEvent;
use Sendora\Events\DeferredEvent;
use Sendora\Events\DeliveredEvent;
use Sendora\Events\OpenedEvent;
use Sendora\Events\SpamComplaintEvent;
use Sendora\Events\UnsubscribedEvent;
use Sendora\Exception\InvalidArgumentException;
use Sendora\Exception\WebhookVerificationException;
use Sendora\WebhookVerifier;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * A request parser for Symfony's Webhook component, which reads each of
 * Sendora's webhook deliveries into a remote event. Register it as a
 * service, and route a type to it with the webhook's signing secret:
 *
 * ```yaml
 * framework:
 *     webhook:
 *         routing:
 *             sendora:
 *                 service: Sendora\Mailer\Webhook\SendoraRequestParser
 *                 secret: '%env(SENDORA_WEBHOOK_SECRET)%'
 * ```
 *
 * It checks `Sendora-Signature` as `WebhookVerifier::verify()` does. The
 * seven events about a message become Symfony's `MailerDeliveryEvent` and
 * `MailerEngagementEvent`, whose id is Sendora's message id. Every other
 * event, `inbound` among them, becomes a `RemoteEvent` named after it,
 * whose id is the delivery's. Each event's payload is the delivery as it
 * came, so key your handling on the payload's `id`: a delivery arrives at
 * least once.
 */
final class SendoraRequestParser extends AbstractRequestParser
{
    /**
     * @param int $tolerance how old a signature may be, in seconds, either way
     */
    public function __construct(
        private readonly int $tolerance = WebhookVerifier::TOLERANCE,
    ) {}

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new MethodRequestMatcher('POST');
    }

    /**
     * @throws RejectWebhookException with 400 when the signature is missing, malformed, wrong or outside the tolerance, or the signed body is no event
     * @throws InvalidArgumentException the secret is empty, as an unset variable gives it, or the tolerance is negative
     */
    protected function doParse(#[\SensitiveParameter] Request $request, #[\SensitiveParameter] string $secret): RemoteEvent
    {
        if ($secret === '') {
            throw new InvalidArgumentException("The parser needs the webhook's signing secret, as the secret of its type under framework.webhook.routing.");
        }
        try {
            $event = WebhookVerifier::verify($request->getContent(), signature: $request->headers->get('Sendora-Signature'), secret: $secret, tolerance: $this->tolerance);
        } catch (WebhookVerificationException $error) {
            throw new RejectWebhookException(400, $error->getMessage(), $error);
        }

        return match (true) {
            $event instanceof DeliveredEvent => self::delivery(MailerDeliveryEvent::DELIVERED, $event, null),
            $event instanceof BouncedEvent => self::delivery(MailerDeliveryEvent::BOUNCE, $event, $event->details->response ?? $event->details->classification),
            $event instanceof DeferredEvent => self::delivery(MailerDeliveryEvent::DEFERRED, $event, $event->details->response),
            $event instanceof SpamComplaintEvent => self::engagement(MailerEngagementEvent::SPAM, $event),
            $event instanceof UnsubscribedEvent => self::engagement(MailerEngagementEvent::UNSUBSCRIBE, $event),
            $event instanceof OpenedEvent => self::engagement(MailerEngagementEvent::OPEN, $event),
            $event instanceof ClickedEvent => self::engagement(MailerEngagementEvent::CLICK, $event),
            default => new RemoteEvent($event->event, $event->id, $event->toArray()),
        };
    }

    private static function delivery(string $name, DeliveredEvent|BouncedEvent|DeferredEvent $event, ?string $reason): MailerDeliveryEvent
    {
        $delivery = self::filled(new MailerDeliveryEvent($name, $event->messageId, $event->toArray()), $event);
        $delivery->setReason($reason ?? '');

        return $delivery;
    }

    private static function engagement(string $name, SpamComplaintEvent|UnsubscribedEvent|OpenedEvent|ClickedEvent $event): MailerEngagementEvent
    {
        return self::filled(new MailerEngagementEvent($name, $event->messageId, $event->toArray()), $event);
    }

    /**
     * @template T of AbstractMailerEvent
     *
     * @param T $mailerEvent
     *
     * @return T
     */
    private static function filled(
        AbstractMailerEvent $mailerEvent,
        DeliveredEvent|BouncedEvent|DeferredEvent|SpamComplaintEvent|UnsubscribedEvent|OpenedEvent|ClickedEvent $event,
    ): AbstractMailerEvent {
        $mailerEvent->setDate($event->occurredAt);
        $mailerEvent->setRecipientEmail($event->recipient);
        $mailerEvent->setMetadata($event->metadata);
        $mailerEvent->setTags($event->tag === null ? [] : [$event->tag]);

        return $mailerEvent;
    }
}
