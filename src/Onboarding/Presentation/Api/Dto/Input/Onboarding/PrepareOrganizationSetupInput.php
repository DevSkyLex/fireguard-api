<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Dto\Input\Onboarding;

use ApiPlatform\Metadata\ApiProperty;
use Onboarding\Presentation\Api\Serialization\OnboardingSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Durable organization setup recovery.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PrepareOrganizationSetupInput
{
  /**
   * @since 1.0.0
   */
  #[Assert\Uuid]
  #[Assert\NotBlank]
  #[Groups([OnboardingSerializationGroup::WRITE])]
  public string $sessionId = '';

  /**
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['create_organization', 'invite_members', 'create_first_facility', 'create_first_equipment'])]
  #[Groups([OnboardingSerializationGroup::WRITE])]
  public string $stepKey = '';

  /**
   * @since 1.0.0
   *
   * @var list<array{itemKey:string,payload:array<string,mixed>}>
   */
  #[ApiProperty(openapiContext: [
    'type' => 'array',
    'minItems' => 1,
    'maxItems' => 5,
    'description' => 'One organization/equipment item or up to five invitation/facility items. Inputs are exact owner-resource wire payloads; omitted pending keys are replaced while completed keys remain durable.',
    'items' => [
      'type' => 'object',
      'required' => ['itemKey', 'payload'],
      'additionalProperties' => false,
      'properties' => [
        'itemKey' => ['type' => 'string', 'pattern' => '^[a-zA-Z0-9_-]{1,80}$'],
        'payload' => ['type' => 'object', 'description' => 'Creation fields belonging to stepKey, excluding organization and setup context. Canonical defaults are returned in setupOperations.'],
      ],
    ],
  ])]
  #[Assert\Count(min: 1, max: 5)]
  #[Groups([OnboardingSerializationGroup::WRITE])]
  public array $items = [];
}
