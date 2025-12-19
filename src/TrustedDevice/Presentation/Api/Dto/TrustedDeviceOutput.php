<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Dto;

use DateTimeImmutable;

/**
 * DTO TrustedDeviceOutput.
 */
final class TrustedDeviceOutput
{
    public string $id;
    public string $name;
    public DateTimeImmutable $lastUsedAt;
    public DateTimeImmutable $expiresAt;
    public DateTimeImmutable $createdAt;
}
