<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/**
 * A batch's answer: every message answered on its own, in order. Tell a
 * result apart with `instanceof`, or by its `status`.
 */
final readonly class BatchResult implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param list<AcceptedBatchItem|RefusedBatchItem> $results one result per message, accepted or refused; the batch as a whole never fails halfway
     */
    public function __construct(
        public array $results,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        $results = [];
        foreach (Json::objects($json, 'results') as $result) {
            $results[] = Json::choice($result, 'status', ['accepted', 'error']) === 'accepted'
                ? AcceptedBatchItem::fromWire($result)
                : RefusedBatchItem::fromWire($result);
        }

        return new self(results: $results, json: $json);
    }
}
