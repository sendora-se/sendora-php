<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** One field the API refused, on `invalid_request`. */
final readonly class ValidationIssue implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $path the field, dotted, such as `to.0.email`; empty when the whole body is wrong
     * @param string $message what is wrong with it
     */
    public function __construct(
        public string $path,
        public string $message,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            path: Json::string($json, 'path'),
            message: Json::string($json, 'message'),
            json: $json,
        );
    }
}
