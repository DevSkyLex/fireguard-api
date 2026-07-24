<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateInterventionLabelInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateInterventionLabelInput
{
  /**
   * Property organization.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[ApiProperty(example: '/api/organizations/550e8400-e29b-41d4-a716-446655440000')]
  public string $organization = '';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 50)]
  public string $name = '';

  /**
   * Property color.
   *
   * A `#rrggbb` hex color string.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'The color must be a hex string in the #rrggbb format.')]
  #[ApiProperty(example: '#3b82f6')]
  public string $color = '';
}
