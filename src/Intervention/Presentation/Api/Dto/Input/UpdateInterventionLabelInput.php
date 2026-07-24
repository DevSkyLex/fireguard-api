<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateInterventionLabelInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateInterventionLabelInput
{
  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 1, max: 50)]
  public ?string $name = null;

  /**
   * Property color.
   *
   * A `#rrggbb` hex color string.
   *
   * @since 1.0.0
   */
  #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'The color must be a hex string in the #rrggbb format.')]
  #[ApiProperty(example: '#3b82f6')]
  public ?string $color = null;
}
