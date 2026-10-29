<?php

declare(strict_types=1);

namespace Sendora\Internal;

use Sendora\Exception\ApiStatusException;
use Sendora\Exception\ResponseValidationException;
use Sendora\Exception\SendoraException;
use Sendora\Types\ErrorCode;

/**
 * A reply as the value a call answers, or the exception it amounts to.
 *
 * @internal
 */
final class Answers
{
    /**
     * A call's JSON object.
     *
     * @throws SendoraException the reply is a refusal, a redirect or no JSON object
     */
    public static function json(#[\SensitiveParameter] Reply $reply, string $baseUrl): JsonObject
    {
        self::refuseUnlessSuccess($reply, $baseUrl);

        return JsonObject::parse($reply->body) ?? throw self::unreadable($reply->status, 'without a JSON object');
    }

    /**
     * A download's bytes. A received attachment may be empty, so only a 204
     * lacks them.
     *
     * @throws SendoraException the reply is a refusal, a redirect or a 204
     */
    public static function bytes(#[\SensitiveParameter] Reply $reply, string $baseUrl): string
    {
        self::refuseUnlessSuccess($reply, $baseUrl);
        if ($reply->status === 204) {
            throw self::unreadable($reply->status, 'without the bytes');
        }

        return $reply->body;
    }

    /**
     * Nothing, for a call that answers nothing.
     *
     * @throws SendoraException the reply is a refusal or a redirect
     */
    public static function nothing(#[\SensitiveParameter] Reply $reply, string $baseUrl): void
    {
        self::refuseUnlessSuccess($reply, $baseUrl);
    }

    private static function refuseUnlessSuccess(#[\SensitiveParameter] Reply $reply, string $baseUrl): void
    {
        $status = $reply->status;
        if ($status >= 200 && $status < 300) {
            return;
        }
        if ($status >= 300 && $status < 400) {
            throw new ApiStatusException(
                "The API answered a redirect, which the SDK never follows; check baseUrl ({$baseUrl}).",
                ErrorCode::UnexpectedResponse->value,
                status: $status,
            );
        }

        throw ErrorReader::fromAnswer($status, JsonObject::parse($reply->body), $reply->headers['retry-after'] ?? null);
    }

    private static function unreadable(int $status, string $what): ResponseValidationException
    {
        return new ResponseValidationException(
            "The API answered {$status} {$what}. The call took effect.",
            ErrorCode::UnexpectedResponse->value,
            status: $status,
        );
    }
}
