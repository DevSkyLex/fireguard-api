<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganization;

use DateTimeImmutable;
use Organization\Domain\ValueObject\OrganizationSettings;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationResult class.
   *
   * @since 1.0.0
   *
   * @param string $id the organization ID
   * @param string $name the organization name
   * @param string $slug the organization slug
   * @param string $ownerUserId the owner user ID
   * @param string $createdByUserId the creator user ID
   * @param string $status the organization status
   * @param bool $isActive whether the organization is active
   * @param DateTimeImmutable $createdAt the creation date
   * @param DateTimeImmutable $updatedAt the update date
   * @param int $memberCount the member count
   * @param ?string $description the organization description
   * @param ?string $logoUrl the organization logo URL
   * @param ?OrganizationSettings $settings the structured organization settings
   * @param ?string $planId the assigned plan identifier
   * @param ?string $planName the assigned plan display name
   * @param ?string $country the legal country (ISO 3166-1 alpha-2)
   * @param ?string $legalType the legal entity type
   * @param ?string $legalName the registered legal name
   * @param ?string $registrationNumber the company/registration number
   * @param ?string $vatNumber the VAT number
   * @param ?bool $isOwner whether the REQUESTING user owns the organization; null when the use case does not resolve caller membership
   * @param list<GetOrganizationCallerRoleResult>|null $roles the organization roles assigned to the REQUESTING user; null when the use case does not resolve caller membership
   */
  public function __construct(
    public string $id,
    public string $name,
    public string $slug,
    public string $ownerUserId,
    public string $createdByUserId,
    public string $status,
    public bool $isActive,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public int $memberCount = 0,
    public ?string $description = null,
    public ?string $logoUrl = null,
    public ?OrganizationSettings $settings = null,
    public ?string $planId = null,
    public ?string $planName = null,
    public ?string $country = null,
    public ?string $legalType = null,
    public ?string $legalName = null,
    public ?string $registrationNumber = null,
    public ?string $vatNumber = null,
    public ?bool $isOwner = null,
    public ?array $roles = null,
  ) {
  }
  // #endregion
}
