<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Output\Facility;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO GeocodeAddressOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GeocodeAddressOutput
{
  // #region Properties
  /**
   * Property displayName.
   *
   * The provider's canonical display name for the match — doubles as the
   * operation's identifier: a geocode lookup has no natural id, mirroring
   * how {@see FacilityPlanOverlayOutput} promotes its attachmentId.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $displayName = '';

  /**
   * Property latitude.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public float $latitude = 0.0;

  /**
   * Property longitude.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public float $longitude = 0.0;
  // #endregion
}
