<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Domain\ValueObject\OrganizationLegalType;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpsertOrganizationLegalProfileInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpsertOrganizationLegalProfileInput
{
  // #region Properties
  /**
   * Property countryCode.
   *
   * @since 1.0.0
   */
  #[Assert\Regex(pattern: '/^[A-Za-z]{2}$/', message: 'Country code must be a valid ISO 3166-1 alpha-2 value.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Legal country code (ISO 3166-1 alpha-2). Defaults to FR when omitted.', required: false, example: 'FR')]
  public ?string $countryCode = null;

  /**
   * Property legalType.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Legal type is required.')]
  #[Assert\Choice(callback: [OrganizationLegalType::class, 'values'])]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Organization legal type', required: true, example: 'company')]
  public string $legalType = '';

  /**
   * Property legalName.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Legal name is required.')]
  #[Assert\Length(min: 2, max: 160)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Registered legal name', required: true, example: 'Fireguard SAS')]
  public string $legalName = '';

  /**
   * Property registrationNumber.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 64)]
  #[Assert\Regex(pattern: '/^[A-Za-z0-9\-\/. ]+$/', message: 'Registration number contains unsupported characters.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Registration number (required for some legal types)', required: false, example: 'RCS-PAR-123456789')]
  public ?string $registrationNumber = null;

  /**
   * Property vatNumber.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 64)]
  #[Assert\Regex(pattern: '/^[A-Za-z0-9\-\/. ]+$/', message: 'VAT number contains unsupported characters.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'VAT number', required: false, example: 'FR00123456789')]
  public ?string $vatNumber = null;
  // #endregion
}
