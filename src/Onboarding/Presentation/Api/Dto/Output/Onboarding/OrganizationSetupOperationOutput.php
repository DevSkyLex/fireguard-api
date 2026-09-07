<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Dto\Output\Onboarding;

use ApiPlatform\Metadata\ApiProperty;
use Onboarding\Presentation\Api\Serialization\OnboardingSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Durable organization setup recovery.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationSetupOperationOutput
{
  /**
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  public string $stepKey = '';

  /**
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  public string $itemKey = '';

  /**
   * @since 1.0.0
   *
   * @var array<string,mixed>
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  public array $payload = [];

  /**
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  public ?string $resourceId = null;

  /**
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(openapiContext: ['type' => 'string', 'enum' => ['prepared', 'completed']])]
  public string $status = 'prepared';
}
