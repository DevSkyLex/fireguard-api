<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\RestoreFacility;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

final readonly class RestoreFacilityResult implements ResultMessage
{
  /**
   * @param array<string, mixed> $metadata
   */
  public function __construct(
    public string $facilityId,
    public string $organizationId,
    public ?string $parentFacilityId,
    public string $type,
    public string $name,
    public ?string $code,
    public string $status,
    public ?string $address,
    public array $metadata,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
}
