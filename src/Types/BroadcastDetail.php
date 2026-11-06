<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;

/** A broadcast, how far it has come and what tracking recorded of it. */
final readonly class BroadcastDetail extends Broadcast
{
    /**
     * @param string $broadcastId the broadcast's id
     * @param string $streamId the broadcast stream its messages go on
     * @param string $status where it stands; `BroadcastStatus` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param string $from the sender's address
     * @param string $subject the subject line
     * @param null|string $tag the label it was sent with
     * @param array<array-key, string> $metadata the pairs it was sent with, their keys as they were sent
     * @param int $total the messages stored, after suppressed addresses were dropped
     * @param int $released the messages handed to the mail server so far
     * @param int $failed the messages that could not be handed over, cancelled ones included
     * @param int $suppressed the addresses dropped for standing on the stream's suppression list
     * @param \DateTimeImmutable $submittedAt when it was accepted, in UTC
     * @param null|\DateTimeImmutable $completedAt when nothing was left to send, or it was cancelled
     * @param int $opened the recipients with an open, machines' included, or a click that was likely a person's
     * @param int $openedByPeople those of them likely to be people
     * @param int $clicked the recipients who clicked a link, machines' included
     * @param int $clickedByPeople those of them likely to be people
     * @param list<BroadcastLink> $links each tracked link that has a click, by its index, counted from the clicks each recipient keeps
     */
    public function __construct(
        string $broadcastId,
        string $streamId,
        string $status,
        string $from,
        string $subject,
        ?string $tag,
        array $metadata,
        int $total,
        int $released,
        int $failed,
        int $suppressed,
        \DateTimeImmutable $submittedAt,
        ?\DateTimeImmutable $completedAt,
        public int $opened,
        public int $openedByPeople,
        public int $clicked,
        public int $clickedByPeople,
        public array $links,
        ?JsonObject $json = null,
    ) {
        parent::__construct(
            $broadcastId,
            $streamId,
            $status,
            $from,
            $subject,
            $tag,
            $metadata,
            $total,
            $released,
            $failed,
            $suppressed,
            $submittedAt,
            $completedAt,
            $json,
        );
    }

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            broadcastId: Json::string($json, 'broadcastId'),
            streamId: Json::string($json, 'streamId'),
            status: Json::string($json, 'status'),
            from: Json::string($json, 'from'),
            subject: Json::string($json, 'subject'),
            tag: Json::nullableString($json, 'tag'),
            metadata: Json::map($json, 'metadata'),
            total: Json::int($json, 'total'),
            released: Json::int($json, 'released'),
            failed: Json::int($json, 'failed'),
            suppressed: Json::int($json, 'suppressed'),
            submittedAt: Json::time($json, 'submittedAt'),
            completedAt: Json::nullableTime($json, 'completedAt'),
            opened: Json::int($json, 'opened'),
            openedByPeople: Json::int($json, 'openedByPeople'),
            clicked: Json::int($json, 'clicked'),
            clickedByPeople: Json::int($json, 'clickedByPeople'),
            links: \array_map(BroadcastLink::fromWire(...), Json::objects($json, 'links')),
            json: $json,
        );
    }
}
