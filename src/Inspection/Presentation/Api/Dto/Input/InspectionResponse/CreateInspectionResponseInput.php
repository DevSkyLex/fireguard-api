<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Input\InspectionResponse;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateInspectionResponseInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateInspectionResponseInput
{
  /**
   * Property organization.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  public string $organization = '';

  /**
   * Property mission.
   *
   * @since 1.0.0
   */
  public ?string $mission = null;

  /**
   * Property inspection.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  public string $inspection = '';

  /**
   * Property clientId.
   *
   * @since 1.0.0
   */
  #[Assert\Uuid]
  public ?string $clientId = null;

  /**
   * Property itemKey.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 160)]
  public string $itemKey = '';

  /**
   * Property value.
   *
   * @since 1.0.0
   */
  public mixed $value = null;
}
