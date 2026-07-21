<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Output\Facility;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO FacilityOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityOutput
{
  /**
   * Property intervention.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  public ?string $intervention = null;

  /**
   * Property recordStatus.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  public string $recordStatus = 'published';

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  public int $revision = 1;

  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $organizationId = '';

  /**
   * Property parentFacilityId.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $parentFacilityId = null;

  /**
   * Property hasChildren.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $hasChildren = false;

  /**
   * Property equipmentCount.
   *
   * Active (non-decommissioned, published) equipment assigned to this facility.
   * Read through the Equipment module's outbound port — the Facility module
   * owns no equipment data of its own.
   *
   * @since 1.1.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Active equipment assigned to this facility')]
  public int $equipmentCount = 0;

  /**
   * Property type.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $type = '';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $name = '';

  /**
   * Property code.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $code = null;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = '';

  /**
   * Property address.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $address = null;

  /**
   * Property latitude.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?float $latitude = null;

  /**
   * Property longitude.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?float $longitude = null;

  /**
   * Property metadata.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $metadata = [];

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $updatedAt = '';
  // #endregion
}
