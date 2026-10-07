<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Equipment\Presentation\Api\Dto\Input\Equipment\ReplaceEquipmentInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\ReplaceEquipmentOutput;
use Equipment\Presentation\Api\Operation\EquipmentReplacementOperations;
use Equipment\Presentation\Api\Processor\Equipment\ReplaceEquipmentProcessor;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;

/**
 * Class EquipmentReplacementResource
 *
 * Organization-scoped atomic equipment replacement, preserving the historical asset.
 *
 * @category Resource
 */
#[ApiResource(
  shortName: 'EquipmentReplacement',
  operations: [
    new Post(
      name: EquipmentReplacementOperations::REPLACE,
      uriTemplate: '/organizations/{organizationId}/equipment/{equipmentId}/replace',
      read: false,
      input: ReplaceEquipmentInput::class,
      output: ReplaceEquipmentOutput::class,
      processor: ReplaceEquipmentProcessor::class,
      status: 200,
      security: "is_granted('ROLE_USER')",
      denormalizationContext: ['groups' => [EquipmentSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Replace equipment',
        description: 'Retires a published asset and transfers its placement to an unused stock asset or atomically creates its successor. Replays return the original result.',
        responses: [
          200 => new Response(description: 'Replacement committed or replayed'),
          403 => new Response(description: 'Missing equipment write permission'),
          404 => new Response(description: 'Organization or equipment unavailable'),
          409 => new Response(description: 'Replacement lifecycle or replay conflict'),
          422 => new Response(description: 'Invalid successor or operation identity'),
        ],
      ),
    ),
  ],
)]
final class EquipmentReplacementResource
{
}
