<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class UpdateOrganizationRegisteredAddressInput
 *
 * Carries a whole registered office replacement, with every component optional.
 *
 * @category DTO
 */
final class UpdateOrganizationRegisteredAddressInput
{
  // #region Properties
  /**
   * Property line1
   */
  #[Assert\Length(max: 255, normalizer: 'trim')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(required: false, schema: [
    'type' => ['string', 'null'],
    'description' => 'Optional first address line; maximum 255 characters after trimming. Blank values become null.',
  ])]
  public ?string $line1 = null;

  /**
   * Property line2
   */
  #[Assert\Length(max: 255, normalizer: 'trim')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(required: false, schema: [
    'type' => ['string', 'null'],
    'description' => 'Optional second address line; maximum 255 characters after trimming. Blank values become null.',
  ])]
  public ?string $line2 = null;

  /**
   * Property postalCode
   */
  #[Assert\Length(max: 32, normalizer: 'trim')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(required: false, schema: [
    'type' => ['string', 'null'],
    'description' => 'Optional postal code; maximum 32 characters after trimming. Blank values become null.',
  ])]
  public ?string $postalCode = null;

  /**
   * Property city
   */
  #[Assert\Length(max: 128, normalizer: 'trim')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(required: false, schema: [
    'type' => ['string', 'null'],
    'description' => 'Optional city; maximum 128 characters after trimming. Blank values become null.',
  ])]
  public ?string $city = null;

  /**
   * Property region
   */
  #[Assert\Length(max: 128, normalizer: 'trim')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(required: false, schema: [
    'type' => ['string', 'null'],
    'description' => 'Optional region; maximum 128 characters after trimming. Blank values become null.',
  ])]
  public ?string $region = null;

  /**
   * Property countryCode
   *
   * Recognized ISO codes are enforced by the domain, including for non-HTTP callers.
   */
  #[Assert\Regex(pattern: '/^\s*(?:[A-Za-z]{2})?\s*$/', message: 'Country must be an ISO 3166-1 alpha-2 code.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(required: false, example: 'FR')]
  public ?string $countryCode = null;

  // #endregion

  // #region Methods
  /**
   * Method toArray
   *
   * @access public
   *
   * @return array{line1: ?string, line2: ?string, postalCode: ?string, city: ?string, region: ?string, countryCode: ?string} the replacement components
   */
  public function toArray(): array
  {
    return [
      'line1' => $this->line1,
      'line2' => $this->line2,
      'postalCode' => $this->postalCode,
      'city' => $this->city,
      'region' => $this->region,
      'countryCode' => $this->countryCode,
    ];
  }
  // #endregion
}
