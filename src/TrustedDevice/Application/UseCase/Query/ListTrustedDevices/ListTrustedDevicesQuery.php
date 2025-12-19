<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\ListTrustedDevices;

/**
 * Query ListTrustedDevicesQuery.
 */
final readonly class ListTrustedDevicesQuery
{
    public function __construct(
        public string $userId,
    ) {
    }
}
