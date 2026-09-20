<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

/**
 * WriteTimeEntryInput.
 *
 * @category Intervention
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WriteTimeEntryInput
{
  /**
   * @since 1.0.0
   */
  #[\Symfony\Component\Validator\Constraints\Uuid]
  public ?string $id = null;

  /**
   * @since 1.0.0
   */
  #[\Symfony\Component\Validator\Constraints\Uuid]
  public ?string $memberId = null;

  /**
   * @since 1.0.0
   */
  #[\Symfony\Component\Validator\Constraints\NotBlank]
  #[\Symfony\Component\Validator\Constraints\Date]
  public string $workedOn = '';

  /**
   * @since 1.0.0
   */
  #[\Symfony\Component\Validator\Constraints\Range(min: 1, max: 1440)]
  public int $minutes = 0;

  /**
   * @since 1.0.0
   */
  #[\Symfony\Component\Validator\Constraints\Length(max: 2000)]
  public ?string $note = null;
}
