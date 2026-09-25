<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

/** Delivery channels for organization notifications. */
final readonly class OrganizationNotificationChannels
{
  public function __construct(
    public bool $emailEnabled = true,
    public bool $inAppEnabled = true,
  ) {
  }
}
