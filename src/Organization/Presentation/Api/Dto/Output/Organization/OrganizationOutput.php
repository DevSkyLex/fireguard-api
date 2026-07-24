<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $name = '';

  /**
   * Property slug.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $slug = '';

  /**
   * Property ownerUserId.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $ownerUserId = '';

  /**
   * Property createdByUserId.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdByUserId = '';

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = '';

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $isActive = true;

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $description = null;

  /**
   * Property logoUrl.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $logoUrl = null;

  /**
   * Property memberCount.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $memberCount = 0;

  /**
   * Property settings.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?OrganizationSettingsOutput $settings = null;

  /**
   * Property planId.
   *
   * Identifier of the assigned subscription plan.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $planId = null;

  /**
   * Property planName.
   *
   * Display name of the assigned subscription plan.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $planName = null;

  /**
   * Property country.
   *
   * Legal country of the organization (ISO 3166-1 alpha-2). Part of the
   * optional legal profile used on reports, invoices and compliance
   * documents.
   *
   * @since 1.4.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $country = null;

  /**
   * Property legalType.
   *
   * Legal entity type. See `GET /api/organizations/legal-types` for the
   * supported values and their labels.
   *
   * @since 1.4.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $legalType = null;

  /**
   * Property legalName.
   *
   * Registered legal name, which may differ from the organization's display
   * `name`.
   *
   * @since 1.4.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $legalName = null;

  /**
   * Property registrationNumber.
   *
   * Company/registration number in the jurisdiction identified by `country`.
   *
   * @since 1.4.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $registrationNumber = null;

  /**
   * Property vatNumber.
   *
   * @since 1.4.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $vatNumber = null;

  /**
   * Property isOwner.
   *
   * Whether the authenticated user is the owner of this organization.
   * Resolved on the user's organization list (`GET /api/organizations`);
   * `null` on operations that do not resolve caller membership.
   *
   * @since 1.5.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?bool $isOwner = null;

  /**
   * Property roles.
   *
   * Organization roles assigned to the authenticated user's membership
   * (empty when the caller is a member without any assigned role).
   * Resolved on the user's organization list (`GET /api/organizations`);
   * `null` on operations that do not resolve caller membership.
   *
   * @since 1.5.0
   *
   * @var list<OrganizationMembershipRoleOutput>|null
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?array $roles = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $updatedAt = '';
  // #endregion
}
