<?php

declare(strict_types=1);

namespace Sendora\Resources;

use Sendora\Exception\ConflictException;
use Sendora\Exception\InvalidArgumentException;
use Sendora\Exception\NotFoundException;
use Sendora\Exception\SendoraException;
use Sendora\Exception\UnprocessableEntityException;
use Sendora\Internal\Call;
use Sendora\Internal\Caller;
use Sendora\Internal\Expect;
use Sendora\Internal\Pages;
use Sendora\Internal\Wire;
use Sendora\Types\CreatedWebhook;
use Sendora\Types\CreatedWebhookSecret;
use Sendora\Types\Delivery;
use Sendora\Types\DeliveryPage;
use Sendora\Types\Webhook;
use Sendora\Types\WebhookList;

/**
 * Registers the server's webhooks, reads their deliveries and rolls their
 * signing secrets.
 *
 * @final
 */
class Webhooks
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * Registers an https URL on a public host to receive the server's
     * events. The answer holds the signing secret, shown this once: keep
     * it beside the key, and verify every delivery with it. A create is
     * never repeated after a lost connection, which may have created the
     * webhook.
     *
     * ```php
     * $created = $sendora->webhooks->create(
     *     url: 'https://example.se/hooks/sendora',
     *     events: ['delivered', 'bounced', 'spam_complaint'],
     * );
     * $vault->store('sendora-webhook', $created->secret);
     * ```
     *
     * @param string $url where the events are posted: https, without credentials or a fragment
     * @param null|list<string> $events the events to deliver, as `WebhookEventName` lists them; every event when left out
     * @param null|string $streamId the one stream whose message events it receives; every stream when left out. Events about usage arrive either way
     * @param null|string $inboundContent how the `inbound` event carries a received message, as `InboundContent` lists them; `full` when left out
     *
     * @throws ConflictException the server has a webhook for the URL (`webhook_exists`, with `existingId`)
     * @throws UnprocessableEntityException no stream of this server has the id (`stream_not_found`)
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function create(string $url, ?array $events = null, ?string $streamId = null, ?string $inboundContent = null): CreatedWebhook
    {
        $body = Wire::body(['url' => $url, 'events' => $events, 'streamId' => $streamId, 'inboundContent' => $inboundContent]);

        return $this->caller->json(
            new Call('POST', '/v1/webhooks', idempotent: false, body: $body),
            CreatedWebhook::fromWire(...),
        );
    }

    /**
     * Every webhook of the server.
     *
     * ```php
     * foreach ($sendora->webhooks->list()->webhooks as $webhook) {
     *     echo $webhook->url, "\n";
     * }
     * ```
     *
     * @throws SendoraException the call failed
     */
    public function list(): WebhookList
    {
        return $this->caller->json(
            new Call('GET', '/v1/webhooks', idempotent: true),
            WebhookList::fromWire(...),
        );
    }

    /**
     * One webhook by its id.
     *
     * ```php
     * $webhook = $sendora->webhooks->get($webhookId);
     * ```
     *
     * @throws NotFoundException no webhook of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function get(string $webhookId): Webhook
    {
        return $this->caller->json(
            new Call('GET', Wire::path('/v1/webhooks/{}', $webhookId), idempotent: true),
            Webhook::fromWire(...),
        );
    }

    /**
     * Removes the webhook, and the deliveries still pending go with it. A
     * delete repeated after a lost answer whose first attempt took effect
     * throws `not_found`.
     *
     * ```php
     * $sendora->webhooks->delete($webhookId);
     * ```
     *
     * @throws NotFoundException no webhook of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function delete(string $webhookId): void
    {
        $this->caller->nothing(new Call('DELETE', Wire::path('/v1/webhooks/{}', $webhookId), idempotent: true, expect: Expect::Nothing));
    }

    /**
     * One page of the events handed to the webhook, newest first, each
     * with its last attempt's outcome. Pass the page's `next` as `after`
     * for the following page, or use `deliveriesAll()`.
     *
     * ```php
     * $dead = $sendora->webhooks->deliveries($webhookId, status: DeliveryStatus::Dead->value);
     * ```
     *
     * @param null|string $status only deliveries in this state, as `DeliveryStatus` lists them
     * @param null|int $limit the page size, 1 to 1000; 100 when left out
     * @param null|string $after the `next` of the previous page
     *
     * @throws NotFoundException no webhook of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function deliveries(string $webhookId, ?string $status = null, ?int $limit = null, ?string $after = null): DeliveryPage
    {
        $query = \array_filter(['status' => $status, 'limit' => $limit, 'after' => $after], static fn(int|string|null $value): bool => $value !== null);

        return $this->caller->json(
            new Call('GET', Wire::path('/v1/webhooks/{}/deliveries', $webhookId), idempotent: true, query: $query),
            DeliveryPage::fromWire(...),
        );
    }

    /**
     * Every delivery the filter matches, newest first, page by page from
     * `after` on. Each page is a request of its own, repeated on its own
     * when it fails.
     *
     * ```php
     * foreach ($sendora->webhooks->deliveriesAll($webhookId, status: 'dead') as $delivery) {
     *     $sendora->webhooks->replay($webhookId, $delivery->deliveryId);
     * }
     * ```
     *
     * @param null|string $status only deliveries in this state, as `DeliveryStatus` lists them
     * @param null|int $limit the page size, 1 to 1000; 100 when left out
     * @param null|string $after the `next` of the page to start after
     *
     * @return \Generator<int, Delivery, mixed, void>
     *
     * @throws NotFoundException no webhook of this server has the id
     * @throws SendoraException a page's call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`
     */
    public function deliveriesAll(string $webhookId, ?string $status = null, ?int $limit = null, ?string $after = null): \Generator
    {
        return Pages::all(function (?string $cursor) use ($webhookId, $status, $limit): array {
            $page = $this->deliveries($webhookId, status: $status, limit: $limit, after: $cursor);

            return [$page->deliveries, $page->next];
        }, $after);
    }

    /**
     * Queues a delivery again, whatever its state, with the same payload.
     * A replay is never repeated after a lost connection, which may have
     * queued it.
     *
     * ```php
     * $sendora->webhooks->replay($webhookId, $deliveryId);
     * ```
     *
     * @throws NotFoundException no webhook of this server has the id, or it has no delivery with the other
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an id is empty, `.` or `..`; nothing was sent
     */
    public function replay(string $webhookId, string $deliveryId): Delivery
    {
        return $this->caller->json(
            new Call('POST', Wire::path('/v1/webhooks/{}/deliveries/{}/replay', $webhookId, $deliveryId), idempotent: false),
            Delivery::fromWire(...),
        );
    }

    /**
     * Adds a second live secret and returns its value, shown this once.
     * Every delivery then carries a signature for each live secret, the
     * newest first. Switch your receiver to the new secret, then delete
     * the old one. A create is never repeated after a lost connection,
     * which may have created the secret.
     *
     * ```php
     * $created = $sendora->webhooks->createSecret($webhookId);
     * $vault->store('sendora-webhook', $created->secret);
     * ```
     *
     * @throws ConflictException the webhook has two live secrets (`secret_limit`)
     * @throws NotFoundException no webhook of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function createSecret(string $webhookId): CreatedWebhookSecret
    {
        return $this->caller->json(
            new Call('POST', Wire::path('/v1/webhooks/{}/secrets', $webhookId), idempotent: false),
            CreatedWebhookSecret::fromWire(...),
        );
    }

    /**
     * Deletes a secret, so deliveries are no longer signed with it. A
     * delete repeated after a lost answer whose first attempt took effect
     * throws `not_found`.
     *
     * ```php
     * $sendora->webhooks->deleteSecret($webhookId, $oldSecretId);
     * ```
     *
     * @throws ConflictException it is the webhook's last live secret (`last_secret`)
     * @throws NotFoundException no webhook of this server has the id, or it has no secret with the other
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an id is empty, `.` or `..`; nothing was sent
     */
    public function deleteSecret(string $webhookId, string $secretId): void
    {
        $this->caller->nothing(new Call(
            'DELETE',
            Wire::path('/v1/webhooks/{}/secrets/{}', $webhookId, $secretId),
            idempotent: true,
            expect: Expect::Nothing,
        ));
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
