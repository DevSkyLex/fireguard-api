<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Plan;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\PlanSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreatePlanInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreatePlanInput
{
  // #region Properties
  /**
   * Property key.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Plan key is required.')]
  #[Assert\Length(min: 2, max: 60)]
  #[Assert\Regex(pattern: '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', message: 'Plan key must use lowercase letters, numbers and single separators.')]
  #[Groups([PlanSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Stable machine key of the plan', required: true, example: 'pro')]
  public string $key = '';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Plan name is required.')]
  #[Assert\Length(min: 2, max: 120)]
  #[Groups([PlanSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Plan display name', required: true, example: 'Pro')]
  public string $name = '';

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 2000)]
  #[Groups([PlanSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Plan description', required: false)]
  public ?string $description = null;

  /**
   * Property limits.
   *
   * Per-resource quantity caps keyed by resource; an absent resource is unlimited.
   *
   * @var array<string, int>
   *
   * @since 1.0.0
   */
  #[Assert\All([new Assert\Type(type: 'integer', message: 'Quota limits must be integers.'), new Assert\PositiveOrZero(message: 'Quota limits must be zero or greater.')])]
  #[Groups([PlanSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Per-resource quantity caps (members, facilities, equipment, inspections); omit a resource for unlimited', required: false, example: ['members' => 5, 'facilities' => 2])]
  public array $limits = [];

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether the plan can be selected', required: false)]
  public bool $isActive = true;

  /**
   * Property isDefault.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether the plan is the catalog default', required: false)]
  public bool $isDefault = false;

  /**
   * Property sortOrder.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Display order', required: false)]
  public int $sortOrder = 0;
  // #endregion
}
