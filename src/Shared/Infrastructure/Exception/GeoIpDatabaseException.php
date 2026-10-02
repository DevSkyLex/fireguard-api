<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

/**
 * Class GeoIpDatabaseException
 *
 * Reports a failed local GeoIP database download, validation or publication.
 * Operational messages contain no request addresses or provider response content.
 *
 * @category Exception
 */
final class GeoIpDatabaseException extends InfrastructureException
{
}
