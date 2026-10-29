<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/**
 * A message of a batch that was refused, as a single send would be. Each
 * property beyond `errorCode` and `message` is filled on the codes that
 * carry it, and `null` or empty on the rest, as on `SendoraException`.
 */
final readonly class RefusedBatchItem implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param int $index the message's position in the batch
     * @param string $status `error`
     * @param string $errorCode why it was refused, as the API names it in `error`; `ErrorCode` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param string $message the refusal in words
     * @param list<ValidationIssue> $issues on `invalid_request`, each field that is wrong
     * @param list<SuppressedRecipient> $suppressed on `recipient_suppressed`, the suppressed recipients
     * @param null|string $streamId on `recipient_suppressed`, the stream whose list holds them
     * @param list<string> $addresses on `test_address_on_live_server`, the simulator's addresses
     * @param null|string $scope on `rate_limited` and `monthly_cap_reached`, whose limit it is; `LimitScope` lists the values this release knows
     * @param null|int $limit on `rate_limited`, the messages allowed a minute
     * @param null|int $retryAfter on `rate_limited`, the seconds to wait; on `unpaid_limit_reached`, the same while a charge under way makes room
     * @param null|int $cap on `monthly_cap_reached`, the messages allowed a month
     * @param null|int $used on `monthly_cap_reached`, the messages sent this month
     * @param null|\DateTimeImmutable $resetsAt on `monthly_cap_reached`, when the month turns, in UTC
     * @param null|int $unpaidOre on `unpaid_limit_reached`, what the account owes for emails before this one, in hundredths of a krona excluding VAT
     * @param null|int $limitOre on `unpaid_limit_reached`, the most the account may owe for emails, in hundredths of a krona excluding VAT
     * @param null|int $priceOre on `unpaid_limit_reached`, this message's price, in hundredths of a krona excluding VAT
     */
    public function __construct(
        public int $index,
        public string $status,
        #[Values(ErrorCode::class)]
        public string $errorCode,
        public string $message,
        public array $issues = [],
        public array $suppressed = [],
        public ?string $streamId = null,
        public array $addresses = [],
        #[Values(LimitScope::class)]
        public ?string $scope = null,
        public ?int $limit = null,
        public ?int $retryAfter = null,
        public ?int $cap = null,
        public ?int $used = null,
        public ?\DateTimeImmutable $resetsAt = null,
        public ?int $unpaidOre = null,
        public ?int $limitOre = null,
        public ?int $priceOre = null,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            index: Json::int($json, 'index'),
            status: Json::string($json, 'status'),
            errorCode: Json::string($json, 'error'),
            message: Json::string($json, 'message'),
            issues: \array_map(ValidationIssue::fromWire(...), Json::optional($json, 'issues', Json::objects(...)) ?? []),
            suppressed: \array_map(SuppressedRecipient::fromWire(...), Json::optional($json, 'suppressed', Json::objects(...)) ?? []),
            streamId: Json::optional($json, 'streamId', Json::nullableString(...)),
            addresses: Json::optional($json, 'addresses', Json::strings(...)) ?? [],
            scope: Json::optional($json, 'scope', Json::nullableString(...)),
            limit: Json::optional($json, 'limit', Json::nullableInt(...)),
            retryAfter: Json::optional($json, 'retryAfter', Json::nullableInt(...)),
            cap: Json::optional($json, 'cap', Json::nullableInt(...)),
            used: Json::optional($json, 'used', Json::nullableInt(...)),
            resetsAt: Json::optional($json, 'resetsAt', Json::nullableTime(...)),
            unpaidOre: Json::optional($json, 'unpaidOre', Json::nullableInt(...)),
            limitOre: Json::optional($json, 'limitOre', Json::nullableInt(...)),
            priceOre: Json::optional($json, 'priceOre', Json::nullableInt(...)),
            json: $json,
        );
    }
}
