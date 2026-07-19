<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateOrganizationSettingsInput.
 *
 * Partial general & branding payload. Every field is optional; only the
 * provided fields are applied. Send an empty `description` to clear it.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateOrganizationSettingsInput
{
  // #region Properties
  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 2, max: 120)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Organization display name', required: false, example: 'Fireguard Paris')]
  public ?string $name = null;

  /**
   * Property slug.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 3, max: 120)]
  #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Slug must use lowercase letters, numbers and dashes.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Public organization slug', required: false, example: 'fireguard-paris')]
  public ?string $slug = null;

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 2000)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Free-text organization description', required: false)]
  public ?string $description = null;

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether the organization is active', required: false)]
  public ?bool $isActive = null;

  /**
   * Property notifications.
   *
   * @since 1.0.0
   */
  #[Assert\Valid]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Partial organization notification policy', required: false)]
  public ?UpdateOrganizationNotificationsInput $notifications = null;

  /**
   * Property regional.
   *
   * @since 1.0.0
   */
  #[Assert\Valid]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Partial organization regional and formatting settings', required: false)]
  public ?UpdateOrganizationRegionalInput $regional = null;

  /**
   * Property compliance.
   *
   * @since 1.1.0
   */
  #[Assert\Valid]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Partial organization compliance policy (SLAs, inspection periodicities, reminder window)', required: false)]
  public ?UpdateOrganizationComplianceInput $compliance = null;

  /**
   * Property automation.
   *
   * @since 1.1.0
   */
  #[Assert\Valid]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Partial organization automation toggles', required: false)]
  public ?UpdateOrganizationAutomationInput $automation = null;

  /**
   * Property approval.
   *
   * @since 1.2.0
   */
  #[Assert\Valid]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Partial organization four-eyes approval policy', required: false)]
  public ?UpdateOrganizationApprovalInput $approval = null;

  /**
   * Property assistant.
   *
   * @since 1.3.0
   */
  #[Assert\Valid]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Partial organization AI-assistant policy', required: false)]
  public ?UpdateOrganizationAssistantInput $assistant = null;

  /**
   * Property country.
   *
   * ISO 3166-1 alpha-2 legal country code. Send an empty string to clear it.
   *
   * @since 1.4.0
   */
  #[Assert\Regex(pattern: '/^[A-Za-z]{2}$/', message: 'Country must be an ISO 3166-1 alpha-2 code.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'ISO 3166-1 alpha-2 legal country code (empty string clears it)', required: false, example: 'FR')]
  public ?string $country = null;

  /**
   * Property legalType.
   *
   * Legal entity type. See `GET /api/organizations/legal-types` for the
   * supported values. Send an empty string to clear it.
   *
   * @since 1.4.0
   */
  #[Assert\Length(max: 32)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Legal entity type (empty string clears it)', required: false, example: 'limited_liability_company')]
  public ?string $legalType = null;

  /**
   * Property legalName.
   *
   * Registered legal name, which may differ from the organization's display
   * name. Send an empty string to clear it.
   *
   * @since 1.4.0
   */
  #[Assert\Length(max: 255)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Registered legal name (empty string clears it)', required: false, example: 'Fireguard Paris SARL')]
  public ?string $legalName = null;

  /**
   * Property registrationNumber.
   *
   * Company/registration number in the jurisdiction identified by `country`.
   * Send an empty string to clear it.
   *
   * @since 1.4.0
   */
  #[Assert\Length(max: 64)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Company/registration number (empty string clears it)', required: false, example: 'RCS PARIS 812345678')]
  public ?string $registrationNumber = null;

  /**
   * Property vatNumber.
   *
   * Send an empty string to clear it.
   *
   * @since 1.4.0
   */
  #[Assert\Length(max: 64)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'VAT number (empty string clears it)', required: false, example: 'FR12345678901')]
  public ?string $vatNumber = null;
  // #endregion
}
