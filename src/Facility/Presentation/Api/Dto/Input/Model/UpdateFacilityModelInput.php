<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Input\Model;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateFacilityModelInput. Omitted or null associations preserve stored references.
 *
 * @category DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateFacilityModelInput
{
  // #region Properties
  /**
   * @var ?array<string, mixed>
   */
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(required: true, openapiContext: [
    'type' => 'object', 'required' => ['scale', 'rotationDegrees', 'translation'],
    'properties' => [
      'scale' => ['type' => 'number', 'exclusiveMinimum' => 0],
      'rotationDegrees' => ['type' => 'number'],
      'translation' => ['type' => 'object', 'required' => ['x', 'y', 'z'],
        'properties' => ['x' => ['type' => 'number'], 'y' => ['type' => 'number'], 'z' => ['type' => 'number']]],
    ],
  ])]
  #[Assert\NotNull]
  #[Assert\Type('array')]
  public ?array $transform = null;

  /**
   * @var ?list<mixed>
   */
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(required: false, openapiContext: [
    'type' => ['array', 'null'], 'items' => ['type' => 'object', 'required' => ['nodeIndex', 'facilityId'],
      'properties' => ['nodeIndex' => ['type' => 'integer', 'minimum' => 0], 'facilityId' => ['type' => 'string', 'format' => 'uuid']]],
    'description' => 'Omit or use null to preserve stored associations. Nonempty replacements retain unavailable historical nodes unless explicitly removed or reassigned. An empty array clears all associations.',
  ])]
  #[Assert\Type('array')]
  public ?array $bindings = null;

  /**
   * @var list<mixed>
   */
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(required: false, openapiContext: [
    'type' => 'array', 'uniqueItems' => true, 'items' => ['type' => 'integer', 'minimum' => 0],
    'description' => 'Explicitly remove node associations, including unavailable historical targets. An explicit empty bindings array clears all associations.',
  ])]
  #[Assert\Type('array')]
  public array $removeBindingNodeIndices = [];
  // #endregion
}
