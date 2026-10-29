<?php

declare(strict_types=1);

namespace Sendora\Internal;

/**
 * What every answer has besides its own properties: the JSON it was built
 * from, read back through `toArray()` and `json_encode()`. A key newer than
 * the installed release can so be read before an upgrade. The answer keeps
 * it in its constructor's last parameter, `$json`, which `fromWire()` sets.
 *
 * @internal
 */
trait KeepsJson
{
    /**
     * The answer as the API sent it, in its camelCase, unknown keys
     * included. An empty JSON object is an empty array here, while
     * `json_encode()` of the answer writes it back as `{}`. An answer built
     * by hand, such as for a test's double, gives an empty array.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->json?->toArray() ?? [];
    }

    /** The JSON the answer was built from, written back as it came. */
    public function jsonSerialize(): \stdClass
    {
        return $this->json?->copy() ?? new \stdClass();
    }
}
