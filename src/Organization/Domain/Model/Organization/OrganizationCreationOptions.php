<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Organization;

use Organization\Domain\ValueObject\{OrganizationOperatingProfile, OrganizationSettings, OrganizationSlug, PlanId};

/**
 * Optional values supplied when creating an organization.
 *
 * @category Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationCreationOptions
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries optional organization identity, profile and plan settings at creation.
   *
   * @access public
   *
   * @param ?OrganizationSlug $slug optional requested organization slug
   * @param ?string $createdByUserId optional user who created the organization
   * @param ?string $description optional organization description
   * @param ?string $logoUrl optional organization logo URL
   * @param ?OrganizationSettings $settings optional organization settings
   * @param ?PlanId $planId optional plan assigned at creation
   *
   * @return void
   */
  public function __construct(
    public ?OrganizationSlug $slug = null,
    public ?string $createdByUserId = null,
    public ?string $description = null,
    public ?string $logoUrl = null,
    public ?OrganizationSettings $settings = null,
    public ?PlanId $planId = null,
    public OrganizationOperatingProfile $operatingProfile = OrganizationOperatingProfile::OPERATOR,
  ) {
  }
  // #endregion
}
