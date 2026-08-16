<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Output\Facility;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO FacilityPlanOverlayOutput.
 *
 * Extended additively with `equipment` — no existing property changed to
 * make room for it.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityPlanOverlayOutput
{
  // #region Properties
  /**
   * Property attachmentId.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $attachmentId = '';

  /**
   * Property imageWidth.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?int $imageWidth = null;

  /**
   * Property imageHeight.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?int $imageHeight = null;

  /**
   * Property zones.
   *
   * @since 1.0.0
   *
   * @var list<array{facilityId: string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>}>
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $zones = [];

  /**
   * Property equipment.
   *
   * Every equipment item pinned on this plan attachment, resolved
   * cross-module through the Equipment module.
   *
   * @since 1.1.0
   *
   * @var list<array{equipmentId: string, name: string, status: string, x: float, y: float}>
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $equipment = [];
  // #endregion
}
