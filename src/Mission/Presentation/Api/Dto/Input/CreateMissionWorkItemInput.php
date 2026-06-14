<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateMissionWorkItemInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateMissionWorkItemInput
{
  /**
   * Property mission.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  public string $mission = '';

  /**
   * Property action.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['site_setup', 'inventory', 'inspection'])]
  public string $action = 'inventory';

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
   * Property assignee.
   *
   * @since 1.0.0
   */
  public ?string $assignee = null;

  /**
   * Property source.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['planned', 'discovered'])]
  public string $source = 'planned';

  /**
   * Property required.
   *
   * @since 1.0.0
   */
  public bool $required = true;
}
