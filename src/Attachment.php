<?php

declare(strict_types=1);

namespace Sendora;

use Sendora\Exception\InvalidArgumentException;

/**
 * A file for a send, its bytes encoded as the API takes them: base64
 * without line breaks.
 *
 * ```php
 * $sendora->email->send(
 *     from: 'notices@example.se',
 *     to: ['anna@example.com'],
 *     subject: 'Your invoice',
 *     text: 'The invoice is attached.',
 *     attachments: [Attachment::fromFile('/srv/invoices/1042.pdf', contentType: 'application/pdf')],
 * );
 * ```
 *
 * An attachment may also be an array in the API's own shape, whose
 * `content` is already base64.
 */
final class Attachment
{
    private function __construct(
        public readonly string $name,
        public readonly string $contentType,
        #[\SensitiveParameter]
        private readonly string $content,
        public readonly ?string $contentId,
    ) {}

    /**
     * An attachment from bytes in memory.
     *
     * @param null|string $contentId the id that an HTML part's `cid:` reference names, for an inline image
     */
    public static function fromBytes(
        #[\SensitiveParameter]
        string $bytes,
        string $name,
        string $contentType,
        ?string $contentId = null,
    ): self {
        return new self($name, $contentType, \base64_encode($bytes), $contentId);
    }

    /**
     * An attachment from a file, named after the file unless `name` says
     * otherwise, since an upload's file has a temporary name.
     *
     * @param null|string $contentId the id that an HTML part's `cid:` reference names, for an inline image
     *
     * @throws InvalidArgumentException the file cannot be read
     */
    public static function fromFile(
        string $path,
        string $contentType,
        ?string $name = null,
        ?string $contentId = null,
    ): self {
        $bytes = \is_file($path) && \is_readable($path) ? \file_get_contents($path) : false;
        if ($bytes === false) {
            throw new InvalidArgumentException("The attachment's file cannot be read: {$path}");
        }

        return new self($name ?? \basename($path), $contentType, \base64_encode($bytes), $contentId);
    }

    /**
     * The attachment as the API takes it.
     *
     * @internal
     *
     * @return array{name: string, content: string, contentType: string, contentId: null|string}
     */
    public function toWire(): array
    {
        return [
            'name' => $this->name,
            'content' => $this->content,
            'contentType' => $this->contentType,
            'contentId' => $this->contentId,
        ];
    }

    /**
     * Leaves out the content.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['name' => $this->name, 'contentType' => $this->contentType, 'contentId' => $this->contentId];
    }
}
