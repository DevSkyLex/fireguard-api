<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Facility\Domain\Exception\FacilityCustomerAssignmentException;
use Shared\Domain\ValueObject\Uuid;

/** Class FacilityCustomerReference. Pure validation shared by both facility aggregates. @category ValueObject */
final readonly class FacilityCustomerReference
{
  public static function assertValid(?string $customerId, FacilityType $type, ?string $parentId): void
  {
    if (null === $customerId) {
      return;
    }
    new Uuid($customerId);
    if (FacilityType::SITE !== $type || null !== $parentId) {
      throw FacilityCustomerAssignmentException::rootRequired();
    }
  }
}
