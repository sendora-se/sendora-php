<?php

declare(strict_types=1);

namespace Sendora\Events;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** How the recipient unsubscribed. */
final readonly class UnsubscribedDetails implements \JsonSerializable
{
    use KeepsJson;

    /** @param string $source `link` for the page's button, `one_click` for a mail client's request; `UnsubscribeSource` lists the values this release knows, so a `match` over it needs a `default` arm */
    public function __construct(
        #[Values(UnsubscribeSource::class)]
        public string $source,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            source: Json::string($json, 'source'),
            json: $json,
        );
    }
}
