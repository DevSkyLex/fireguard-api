<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationCountryOptionOutput.
 *
 * Represents a country option for UI selects, including a flag image URL
 * and the ISO 3166-1 alpha-2 code to submit to the API.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationCountryOptionOutput
{
  // #region Properties
  /**
   * Property code.
   *
   * ISO 3166-1 alpha-2 country code (e.g. FR).
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $code = '';

  /**
   * Property name.
   *
   * Human-readable country name in English.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $name = '';

  /**
   * Property flagUrl.
   *
   * URL of the country flag image (40px wide PNG via flagcdn.com).
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $flagUrl = '';
  // #endregion
}
