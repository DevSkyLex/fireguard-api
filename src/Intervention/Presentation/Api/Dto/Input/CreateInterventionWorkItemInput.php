<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateInterventionWorkItemInput.
 *
 * @category DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateInterventionWorkItemInput
{
  /**
   * Property operationId. Optional preventive plan identifier.
   */
  #[Assert\Uuid]
  public ?string $operationId = null;

  /**
   * Property occurrenceId. Optional preventive occurrence identifier.
   */
  #[Assert\Uuid]
  public ?string $occurrenceId = null;

  /**
   * Property operationKind. Optional source operation kind.
   */
  #[Assert\Choice(choices: ['control', 'maintenance'])]
  public ?string $operationKind = null;

  /**
   * Property workloadConfirmationToken.
   *
   * @since 1.1.0
   */
  #[Assert\Length(max: 64)]
  public ?string $workloadConfirmationToken = null;

  /**
   * Property estimatedMinutes. Null means not estimated.
   *
   * @since 1.1.0
   */
  #[Assert\PositiveOrZero]
  public ?int $estimatedMinutes = null;

  /**
   * Property workStartsOn. Organization-local date.
   *
   * @since 1.1.0
   */
  #[Assert\Date]
  public ?string $workStartsOn = null;

  /**
   * Property workEndsOn. Organization-local date.
   *
   * @since 1.1.0
   */
  #[Assert\Date]
  public ?string $workEndsOn = null;

  /**
   * Property intervention.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  public string $intervention = '';

  /**
   * Property action.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['site_setup', 'inventory', 'inspection', 'maintenance', 'repair', 'replacement'])]
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
