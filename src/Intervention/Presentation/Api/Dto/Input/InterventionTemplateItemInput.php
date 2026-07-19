<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO InterventionTemplateItemInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionTemplateItemInput
{
  /**
   * Property action.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 60)]
  #[ApiProperty(example: 'inspection')]
  public string $action = '';

  /**
   * Property target.
   *
   * @since 1.0.0
   */
  public ?string $target = null;

  /**
   * Property resultResource.
   *
   * @since 1.0.0
   */
  public ?string $resultResource = null;

  /**
   * Property required.
   *
   * @since 1.0.0
   */
  public bool $required = true;

  /**
   * Property defaultAssignee.
   *
   * @since 1.0.0
   */
  #[ApiProperty(example: '/api/organizations/550e8400-e29b-41d4-a716-446655440000/members/018f0b68-6758-7a12-8a1d-3f0d97f63c13')]
  public ?string $defaultAssignee = null;
}
