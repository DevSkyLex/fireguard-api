<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Organization;

use Organization\Domain\ValueObject\{OrganizationSettings, OrganizationSlug, PlanId};

/**
 * Optional values supplied when creating an organization.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationCreationOptions
{
  public function __construct(
    public ?OrganizationSlug $slug = null,
    public ?string $createdByUserId = null,
    public ?string $description = null,
    public ?string $logoUrl = null,
    public ?OrganizationSettings $settings = null,
    public ?PlanId $planId = null,
  ) {
  }
}
