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
use Sendora\Internal\Literal;
use Sendora\Internal\Wire;
use Sendora\Omit;
use Sendora\Types\Stream;
use Sendora\Types\StreamKind;
use Sendora\Types\StreamList;

/**
 * Adds, reads, renames and archives the streams of the key's server: the
 * default transactional one and any added.
 *
 * @final
 */
class Streams
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * Adds a stream to the server. A transactional or a broadcast stream is
     * open to every account. An inbound stream receives mail at its own
     * address, one live per server, on an active account. A create is
     * never repeated after a lost connection, which may have created the
     * stream.
     *
     * ```php
     * $stream = $sendora->streams->create(kind: StreamKind::Broadcast, name: 'Nyheter');
     * echo $stream->streamId;
     * ```
     *
     * @param StreamKind|string $kind what the stream carries, as `StreamKind` or its value
     * @param string $name 1 to 100 characters, unique within the server
     *
     * @throws ConflictException the name is taken (`stream_exists`, with `existingId`), or the server has a live inbound stream (`inbound_stream_exists`)
     * @throws UnprocessableEntityException the account may not add a stream of this kind yet (`stream_kind_not_allowed`)
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function create(StreamKind|string $kind, string $name): Stream
    {
        $body = Wire::body(['kind' => $kind instanceof StreamKind ? $kind->value : $kind, 'name' => $name]);

        return $this->caller->json(
            new Call('POST', '/v1/streams', idempotent: false, body: $body),
            Stream::fromWire(...),
        );
    }

    /**
     * Every stream of the server, archived ones included, oldest first.
     *
     * ```php
     * foreach ($sendora->streams->list()->streams as $stream) {
     *     echo $stream->name, ' ', $stream->kind, "\n";
     * }
     * ```
     *
     * @throws SendoraException the call failed
     */
    public function list(): StreamList
    {
        return $this->caller->json(
            new Call('GET', '/v1/streams', idempotent: true),
            StreamList::fromWire(...),
        );
    }

    /**
     * One stream by its id.
     *
     * ```php
     * $stream = $sendora->streams->get($streamId);
     * ```
     *
     * @throws NotFoundException no stream of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function get(string $streamId): Stream
    {
        return $this->caller->json(
            new Call('GET', Wire::path('/v1/streams/{}', $streamId), idempotent: true),
            Stream::fromWire(...),
        );
    }

    /**
     * Renames a stream, or sets how long an inbound stream keeps received
     * content. An argument left out stays as it is, and
     * `contentRetentionDays: null` puts the window back to the default of
     * 30 days. The id stays.
     *
     * ```php
     * $sendora->streams->update($streamId, name: 'Driftinformation');
     * $sendora->streams->update($inboundId, contentRetentionDays: 7);
     * ```
     *
     * @param null|string $name 1 to 100 characters, unique within the server
     * @param null|int|Omit $contentRetentionDays an inbound stream's days of content, 1 to 30, or `null` for the default of 30
     *
     * @throws ConflictException another stream of the server has the name (`stream_exists`, with `existingId`)
     * @throws NotFoundException no stream of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent, or the id is empty, `.` or `..`; nothing was sent
     */
    public function update(string $streamId, ?string $name = null, int|Omit|null $contentRetentionDays = Omit::Omit): Stream
    {
        $body = Wire::body([
            'name' => $name,
            'contentRetentionDays' => $contentRetentionDays ?? Literal::Null,
        ]);

        return $this->caller->json(
            new Call('PATCH', Wire::path('/v1/streams/{}', $streamId), idempotent: true, body: $body),
            Stream::fromWire(...),
        );
    }

    /**
     * The stream takes no new messages from now on. What it already holds
     * is still delivered, and archiving again changes nothing. The default
     * stream cannot be archived.
     *
     * ```php
     * $archived = $sendora->streams->archive($streamId);
     * echo $archived->archivedAt?->format(DATE_ATOM);
     * ```
     *
     * @throws ConflictException it is the default stream (`default_stream`)
     * @throws NotFoundException no stream of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function archive(string $streamId): Stream
    {
        return $this->caller->json(
            new Call('POST', Wire::path('/v1/streams/{}/archive', $streamId), idempotent: true),
            Stream::fromWire(...),
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
