<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Input\Facility;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO SetFacilityPlanGeometryInput.
 *
 * `attachmentId` and `points` are required together (to set or replace the
 * geometry), or both null (to clear it) — a PUT-with-null-to-clear shape
 * mirroring `MoveFacilityInput`'s `parentFacilityId`, rather than adding a
 * separate DELETE route for the clear path. Both fields must be present in
 * the request body; pairwise completeness and the deep numeric invariants
 * (point count, coordinate bounds) are handler/domain concerns
 * ({@see \Facility\Domain\ValueObject\PlanGeometry}) — this DTO validates
 * shape only.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SetFacilityPlanGeometryInput
{
  // #region Properties
  /**
   * Property attachmentId.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(allowNull: true, message: 'Attachment ID cannot be blank.')]
  #[Assert\Uuid(message: 'Attachment ID must be a valid UUID.')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Floor plan attachment identifier the geometry is bound to. Required together with points; use null on both to clear.', required: true, example: '550e8400-e29b-41d4-a716-446655440001')]
  public ?string $attachmentId = null;

  /**
   * Property points.
   *
   * @since 1.0.0
   *
   * @var ?list<array{0: float, 1: float}>
   */
  #[Assert\Type(type: 'array')]
  #[Assert\Count(min: 3, minMessage: 'Plan geometry must have at least 3 points.')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Polygon points in normalized 0-1 image coordinates, at least 3. Required together with attachmentId; use null on both to clear.', required: true, example: [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]])]
  public ?array $points = null;
  // #endregion
}
