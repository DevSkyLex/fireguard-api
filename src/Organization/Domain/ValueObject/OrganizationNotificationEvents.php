<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

/** Event categories controlled by the organization notification policy. */
final readonly class OrganizationNotificationEvents
{
  public function __construct(
    public bool $interventionPublished = true,
    public bool $interventionAssigned = true,
    public bool $inspectionDue = true,
    public bool $nonConformityOpened = true,
    public bool $nonConformitySlaBreached = true,
    public bool $memberInvited = true,
    public bool $weeklyDigest = true,
  ) {
  }
}
