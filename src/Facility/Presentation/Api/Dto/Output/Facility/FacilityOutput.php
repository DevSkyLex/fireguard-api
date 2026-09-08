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
   * Property levelIndex.
   *
   * Stacking order of the floor (ground floor = 0, first basement = -1).
   * Nullable, optional everywhere, duplicates tolerated. Readable on both the
   * detail and collection reads, unlike {@see FacilityOutput::$planGeometry}.
   *
   * @since 1.3.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Optional stacking order of the floor (ground floor = 0, first basement = -1)')]
  public ?int $levelIndex = null;

  /**
   * Property planGeometry.
   *
   * Optional spatial geometry `{attachmentId, points}` binding this facility
   * (a zone) to a polygon drawn over an ancestor's floor plan attachment.
   * Populated on detail reads only (`GET .../facilities/{id}` and the
   * canonical single-item read) — never on a list or collection response,
   * where API Platform's null-field omission simply drops it.
   *
   * @since 1.2.0
   *
   * @var ?array{attachmentId: string, points: list<array{0: float, 1: float}>}
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Optional spatial geometry bound to an ancestor floor plan (detail views only)')]
  public ?array $planGeometry = null;

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

  /**
   * Property path.
   *
   * Ancestor breadcrumb ordered root first, direct parent last, excluding
   * this facility. Empty for a root facility. Populated on the detail (item)
   * providers only — {@see FacilitySerializationGroup::READ} is shared with
   * the collection providers, which deliberately leave this an empty list to
   * avoid an N+1 ancestor lookup per row.
   *
   * @since 1.2.0
   *
   * @var list<array{id: string, name: string, type: string}>
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Ancestor breadcrumb, root first, excluding this facility')]
  public array $path = [];
  // #endregion
}
