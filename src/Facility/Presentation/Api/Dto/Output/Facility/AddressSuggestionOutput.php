<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Output\Facility;

use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO AddressSuggestionOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddressSuggestionOutput
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $displayName the canonical address label
   * @param float $latitude the WGS 84 latitude
   * @param float $longitude the WGS 84 longitude
   * @param string $street the street and optional house number
   * @param string $city the locality
   * @param string $region the state or province when available
   * @param string $postalCode the postal code when available
   * @param string $country the country name when available
   * @param string $countryCode the ISO 3166-1 alpha-2 country code when available
   */
  public function __construct(
    #[Groups([FacilitySerializationGroup::READ])]
    public string $displayName,
    #[Groups([FacilitySerializationGroup::READ])]
    public float $latitude,
    #[Groups([FacilitySerializationGroup::READ])]
    public float $longitude,
    #[Groups([FacilitySerializationGroup::READ])]
    public string $street = '',
    #[Groups([FacilitySerializationGroup::READ])]
    public string $city = '',
    #[Groups([FacilitySerializationGroup::READ])]
    public string $region = '',
    #[Groups([FacilitySerializationGroup::READ])]
    public string $postalCode = '',
    #[Groups([FacilitySerializationGroup::READ])]
    public string $country = '',
    #[Groups([FacilitySerializationGroup::READ])]
    public string $countryCode = '',
  ) {
  }
  // #endregion
}
