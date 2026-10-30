<?php

declare(strict_types=1);

namespace Sendora\Internal;

use Sendora\Attachment;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Part\TextPart;

/**
 * A Symfony email as the arguments of `email->send()`, read as Sendora's
 * SMTP submission reads a message: the envelope says who gets it, the
 * headers how it shows, and the tag, metadata and `X-Sendora-` headers
 * what the API's own fields would.
 *
 * @internal
 *
 * @phpstan-type Recipient array{email: string, name: null|string}
 * @phpstan-type SendArguments array{
 *     from: Recipient,
 *     to: list<Recipient>,
 *     subject: string,
 *     streamId: null|string,
 *     cc: list<Recipient>,
 *     bcc: list<Recipient>,
 *     text: null|string,
 *     html: null|string,
 *     headers: array<string, string>,
 *     attachments: list<Attachment>,
 *     tag: null|string,
 *     metadata: array<array-key, string>,
 *     idempotencyKey: null|string,
 * }
 */
final class MailerMapping
{
    /** SMTP submission passes these on, and every other `X-` header but Sendora's own and those it reads. */
    private const PASSED = ['Reply-To', 'In-Reply-To', 'References', 'List-Unsubscribe', 'List-Unsubscribe-Post'];
    private const PASSED_PREFIX = 'X-';
    private const OWN_PREFIX = 'X-Sendora-';
    /** Sendora's own header first: the first of these that a message has gives its tag. */
    private const TAG_HEADERS = ['X-Sendora-Tag', 'X-Tag'];
    /** Sendora's own prefix last, so its entry wins over the other's for the same key. */
    private const METADATA_PREFIXES = ['X-Metadata-', 'X-Sendora-Metadata-'];
    private const STREAM_HEADER = 'X-Sendora-Stream';
    private const IDEMPOTENCY_HEADER = 'X-Sendora-Idempotency-Key';
    private const MAX_HEADER_VALUE = 998;

    /**
     * @return SendArguments
     *
     * @throws TransportException the message cannot be one send: it has two From addresses or two different tags, or text in a charset PHP cannot convert
     */
    public static function arguments(
        #[\SensitiveParameter]
        Email $email,
        #[\SensitiveParameter]
        Envelope $envelope,
        ?string $stream,
    ): array {
        $headers = $email->getHeaders();
        $recipients = self::recipients($email, $envelope);

        return [
            'from' => self::from($email, $envelope),
            'to' => $recipients['to'],
            'subject' => $email->getSubject() ?? '',
            'streamId' => self::first($headers, self::STREAM_HEADER) ?? $stream,
            'cc' => $recipients['cc'],
            'bcc' => $recipients['bcc'],
            'text' => self::body($email->getTextBody(), $email->getTextCharset() ?? 'utf-8'),
            'html' => self::body($email->getHtmlBody(), $email->getHtmlCharset() ?? 'utf-8'),
            'headers' => self::passed($headers),
            'attachments' => self::attachments($email),
            'tag' => self::tag($headers),
            'metadata' => self::metadata($headers),
            'idempotencyKey' => self::first($headers, self::IDEMPOTENCY_HEADER),
        ];
    }

    /**
     * The From header's one address, or the envelope's sender for a message
     * with none, as over SMTP. Laravel puts its return path in the envelope,
     * so the header wins.
     *
     * @return Recipient
     *
     * @throws TransportException
     */
    private static function from(
        #[\SensitiveParameter]
        Email $email,
        #[\SensitiveParameter]
        Envelope $envelope,
    ): array {
        $from = $email->getFrom();
        if (\count($from) > 1) {
            throw new TransportException(\sprintf('A message sent through Sendora has one From address, and this one has %d.', \count($from)));
        }

        return self::recipient($from[0] ?? $envelope->getSender());
    }

    /**
     * Each of the envelope's recipients once, placed by the headers: To
     * first, then Cc, then Bcc. A recipient that no header names was put
     * there by a listener that rewrote the envelope, and goes to `to`. A
     * header's address that the envelope lacks gets nothing.
     *
     * @return array{to: list<Recipient>, cc: list<Recipient>, bcc: list<Recipient>}
     */
    private static function recipients(
        #[\SensitiveParameter]
        Email $email,
        #[\SensitiveParameter]
        Envelope $envelope,
    ): array {
        $named = [];
        foreach (['to' => $email->getTo(), 'cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $kind => $addresses) {
            foreach ($addresses as $address) {
                $named[self::key($address)] ??= [$kind, $address];
            }
        }
        $placed = ['to' => [], 'cc' => [], 'bcc' => []];
        foreach ($envelope->getRecipients() as $recipient) {
            $key = self::key($recipient);
            [$kind, $address] = $named[$key] ?? ['to', $recipient];
            $placed[$kind][$key] ??= self::recipient($address);
        }

        return ['to' => \array_values($placed['to']), 'cc' => \array_values($placed['cc']), 'bcc' => \array_values($placed['bcc'])];
    }

    /** How an envelope's address is matched with a header's: lowercased, its domain IDN-encoded. */
    private static function key(#[\SensitiveParameter] Address $address): string
    {
        return \strtolower($address->getEncodedAddress());
    }

    /**
     * The address with its domain IDN-encoded, as the API takes it, and its
     * name; an empty name is `null`, since the API refuses an empty one.
     *
     * @return Recipient
     */
    private static function recipient(#[\SensitiveParameter] Address $address): array
    {
        $name = $address->getName();

        return ['email' => $address->getEncodedAddress(), 'name' => $name === '' ? null : $name];
    }

    /**
     * A body read whole, a stream rewound where it can be, in UTF-8; `null`
     * for an empty one.
     *
     * @param null|resource|string $body
     *
     * @throws TransportException
     */
    private static function body(#[\SensitiveParameter] mixed $body, string $charset): ?string
    {
        if ($body === null) {
            return null;
        }
        $text = (new TextPart($body))->getBody();
        if ($text === '') {
            return null;
        }
        if (\strcasecmp($charset, 'utf-8') === 0) {
            return $text;
        }
        try {
            $converted = \mb_convert_encoding($text, 'UTF-8', $charset);
        } catch (\ValueError) {
            $converted = false;
        }
        if ($converted === false) {
            throw new TransportException("PHP cannot convert the message's text from {$charset} to UTF-8, which the API takes.");
        }

        return $converted;
    }

    /**
     * Each attachment with content. One without a file name is named
     * `bilaga-<n>`, as over SMTP, and a name with a path keeps its last
     * part, since the API takes a file name alone. Only an inline part has a
     * content id: its own, or else the file name, which the HTML's `cid:`
     * names.
     *
     * @return list<Attachment>
     */
    private static function attachments(#[\SensitiveParameter] Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            $bytes = $part->getBody();
            if ($bytes === '') {
                continue;
            }
            $written = $part->getFilename() ?? '';
            $name = self::lastSegment($written);
            if ($name === '') {
                $name = 'bilaga-' . (\count($attachments) + 1);
            }
            $contentId = null;
            if ($part->getDisposition() === 'inline') {
                $contentId = $part->hasContentId() ? $part->getContentId() : ($written === '' ? $name : $written);
            }
            $attachments[] = Attachment::fromBytes($bytes, name: $name, contentType: $part->getContentType(), contentId: $contentId);
        }

        return $attachments;
    }

    /** What follows a file name's last slash or backslash, as Twig names an image after its template path. */
    private static function lastSegment(string $written): string
    {
        $tail = \strrchr(\strtr($written, '\\', '/'), '/');

        return $tail === false ? $written : \substr($tail, 1);
    }

    /**
     * The headers SMTP submission passes on, the first of each name, with
     * their values as the API takes them.
     *
     * @return array<string, string>
     */
    private static function passed(#[\SensitiveParameter] Headers $headers): array
    {
        $firsts = [];
        foreach (self::all($headers) as $header) {
            $name = $header->getName();
            if (self::passes($name)) {
                $firsts[\strtolower($name)] ??= [$name, self::value($header)];
            }
        }
        $passed = [];
        foreach ($firsts as $lower => [$name, $value]) {
            $passed[$name] = $lower === 'references' ? self::referencesWithin($value) : $value;
        }

        return $passed;
    }

    private static function passes(string $name): bool
    {
        if (self::isOneOf($name, self::PASSED)) {
            return true;
        }
        if (self::isOneOf($name, self::TAG_HEADERS)) {
            return false;
        }
        foreach ([...self::METADATA_PREFIXES, self::OWN_PREFIX] as $prefix) {
            if (self::startsWith($name, $prefix)) {
                return false;
            }
        }

        return self::startsWith($name, self::PASSED_PREFIX);
    }

    /**
     * A header's value decoded and unfolded. Symfony's `getBodyAsString()`
     * folds a long value and encodes words outside ASCII: the API refuses
     * the fold, and would write the encoded words into the message as text.
     * Reply-To's addresses are written out with their names decoded, as
     * SMTP submission writes them, so the API reads them as addresses.
     */
    private static function value(#[\SensitiveParameter] HeaderInterface $header): string
    {
        if ($header instanceof MailboxListHeader) {
            $mailboxes = [];
            foreach ($header->getAddresses() as $address) {
                $mailboxes[] = self::mailbox($address);
            }
            $value = \implode(', ', $mailboxes);
        } elseif ($header instanceof UnstructuredHeader && !$header instanceof ParameterizedHeader) {
            $value = $header->getValue();
        } else {
            $value = $header->getBodyAsString();
        }

        return \trim((string) \preg_replace('/\r?\n[ \t]+/', ' ', $value));
    }

    private static function mailbox(#[\SensitiveParameter] Address $address): string
    {
        $name = $address->getName();

        return $name === '' ? $address->getEncodedAddress() : '"' . \addcslashes($name, '\\"') . "\" <{$address->getEncodedAddress()}>";
    }

    /**
     * A References value longer than the API takes keeps its first id and
     * its latest ones, as RFC 5537 trims one: the thread's root and the
     * replies nearest this one are what a mail client threads by.
     */
    private static function referencesWithin(string $value): string
    {
        if (\strlen($value) <= self::MAX_HEADER_VALUE) {
            return $value;
        }
        $ids = \explode(' ', (string) \preg_replace('/\s+/', ' ', $value));
        $first = \array_shift($ids);
        $latest = [];
        $length = \strlen($first);
        foreach (\array_reverse($ids) as $id) {
            if ($length + 1 + \strlen($id) > self::MAX_HEADER_VALUE) {
                break;
            }
            \array_unshift($latest, $id);
            $length += 1 + \strlen($id);
        }

        return \implode(' ', [$first, ...$latest]);
    }

    /**
     * `X-Sendora-Tag`'s tag if the message has one, else Laravel's and
     * Symfony's `X-Tag`. The same tag twice counts once, since Laravel adds
     * a mailable's tags again each time it is sent, and two different ones
     * are refused rather than one dropped.
     *
     * @throws TransportException
     */
    private static function tag(#[\SensitiveParameter] Headers $headers): ?string
    {
        foreach (self::TAG_HEADERS as $name) {
            $tags = \array_values(\array_unique(self::values($headers, $name)));
            if (\count($tags) > 1) {
                throw new TransportException(\sprintf('A message sent through Sendora has one tag, and its %s headers give %d different ones.', $name, \count($tags)));
            }
            if ($tags !== []) {
                return $tags[0];
            }
        }

        return null;
    }

    /**
     * Each `X-Metadata-<key>` and `X-Sendora-Metadata-<key>` as an entry,
     * its key's case kept. The first header of a key counts, and Sendora's
     * own wins over the other, keys compared without regard to case.
     *
     * @return array<array-key, string>
     */
    private static function metadata(#[\SensitiveParameter] Headers $headers): array
    {
        $entries = [];
        foreach (self::METADATA_PREFIXES as $prefix) {
            $firsts = [];
            foreach (self::all($headers) as $header) {
                $name = $header->getName();
                if (!self::startsWith($name, $prefix)) {
                    continue;
                }
                $key = \substr($name, \strlen($prefix));
                $value = self::value($header);
                if ($key !== '' && $value !== '') {
                    $firsts[\strtolower($key)] ??= [$key, $value];
                }
            }
            $entries = \array_replace($entries, $firsts);
        }
        $metadata = [];
        foreach ($entries as [$key, $value]) {
            $metadata[$key] = $value;
        }

        return $metadata;
    }

    /** The first value of the headers of a name, an empty one counting as none. */
    private static function first(#[\SensitiveParameter] Headers $headers, string $name): ?string
    {
        return self::values($headers, $name)[0] ?? null;
    }

    /** @return list<string> */
    private static function values(#[\SensitiveParameter] Headers $headers, string $name): array
    {
        $values = [];
        foreach (self::all($headers, $name) as $header) {
            $value = self::value($header);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * The headers of a name, or every header, grouped by name in the order
     * each name was first set. Symfony's `all()` declares no item type.
     *
     * @return list<HeaderInterface>
     */
    private static function all(#[\SensitiveParameter] Headers $headers, ?string $name = null): array
    {
        return \array_values(\array_filter(
            \iterator_to_array($headers->all($name), false),
            static fn(mixed $header): bool => $header instanceof HeaderInterface,
        ));
    }

    /** @param list<string> $names */
    private static function isOneOf(string $name, array $names): bool
    {
        return \in_array(\strtolower($name), \array_map(\strtolower(...), $names), true);
    }

    private static function startsWith(string $name, string $prefix): bool
    {
        return \strncasecmp($name, $prefix, \strlen($prefix)) === 0;
    }
}
