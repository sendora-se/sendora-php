<?php

declare(strict_types=1);

namespace Sendora\Mailer;

use Sendora\Exception\InvalidArgumentException;
use Sendora\Sendora;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Makes a `SendoraTransport` from a DSN whose user is the server key:
 *
 * ```
 * MAILER_DSN=sendora+api://sk_…@default?stream=<stream id>
 * ```
 *
 * The scheme is `sendora+api`, or `sendora` for short. The host `default`
 * is Sendora's API, and any other host is reached over HTTPS. `stream` is
 * the only option. Each transport gets the factory's dispatcher and
 * logger: a Symfony app supplies both by registering the factory as a
 * service whose parent is `mailer.transport_factory.abstract`, with the tag
 * `mailer.transport_factory`.
 */
final class SendoraTransportFactory extends AbstractTransportFactory
{
    private const API_HOST = 'api.sendora.se';

    /**
     * @throws UnsupportedSchemeException the scheme is another
     * @throws IncompleteDsnException the DSN names no key
     * @throws InvalidArgumentException the key holds a character that no key has
     */
    public function create(#[\SensitiveParameter] Dsn $dsn): TransportInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, 'sendora', $this->getSupportedSchemes());
        }
        $key = $dsn->getUser() ?? throw new IncompleteDsnException('The DSN names no key: give the server key as its user, as in sendora+api://KEY@default.');
        $host = $dsn->getHost() === 'default' ? self::API_HOST : $dsn->getHost();
        $port = $dsn->getPort();
        $stream = $dsn->getOption('stream');
        $client = new Sendora(token: $key, baseUrl: 'https://' . $host . ($port === null ? '' : ":{$port}"), logger: $this->logger);

        return new SendoraTransport($client, \is_string($stream) && $stream !== '' ? $stream : null, $this->dispatcher, $this->logger);
    }

    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['sendora', 'sendora+api'];
    }
}
