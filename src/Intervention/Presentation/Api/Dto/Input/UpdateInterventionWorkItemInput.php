<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateInterventionWorkItemInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateInterventionWorkItemInput
{
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
   * Property remainingMinutes. Explicit re-estimation only.
   *
   * @since 1.1.0
   */
  #[Assert\PositiveOrZero]
  public ?int $remainingMinutes = null;

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
   * Property status.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['planned', 'in_progress', 'completed', 'skipped'])]
  public ?string $status = null;

  /**
   * Property skipReason.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 2000)]
  public ?string $skipReason = null;
}
