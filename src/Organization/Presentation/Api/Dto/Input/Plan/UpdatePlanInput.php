<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Plan;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\PlanSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdatePlanInput.
 *
 * Every field is optional: an omitted field leaves the current value unchanged.
 * The plan key is immutable and cannot be updated.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdatePlanInput
{
  // #region Properties
  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 2, max: 120)]
  #[Groups([PlanSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Plan display name', required: false)]
  public ?string $name = null;

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
   * @var ?array<string, int>
   *
   * @since 1.0.0
   */
  #[Assert\All([new Assert\Type(type: 'integer', message: 'Quota limits must be integers.'), new Assert\PositiveOrZero(message: 'Quota limits must be zero or greater.')])]
  #[Groups([PlanSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Per-resource quantity caps (members, facilities, equipment, inspections); omit a resource for unlimited', required: false)]
  public ?array $limits = null;

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether the plan can be selected', required: false)]
  public ?bool $isActive = null;

  /**
   * Property isDefault.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether the plan is the catalog default', required: false)]
  public ?bool $isDefault = null;

  /**
   * Property sortOrder.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Display order', required: false)]
  public ?int $sortOrder = null;
  // #endregion
}
