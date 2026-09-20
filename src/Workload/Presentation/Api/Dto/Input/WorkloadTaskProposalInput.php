<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Dto\Input;

/**
 * WorkloadTaskProposalInput.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WorkloadTaskProposalInput
{
  /**
   * @since 1.0.0
   */
  #[\Symfony\Component\Validator\Constraints\NotBlank]
  #[\Symfony\Component\Validator\Constraints\Uuid]
  public string $taskId = '';

  /**
   * @since 1.0.0
   */
  #[\Symfony\Component\Validator\Constraints\Uuid]
  public ?string $memberId = null;

  /**
   * @since 1.0.0
   */
  #[\Symfony\Component\Validator\Constraints\PositiveOrZero]
  public ?int $remainingMinutes = null;

  /**
   * @since 1.0.0
   */
  #[\Symfony\Component\Validator\Constraints\Date]
  public ?string $startsOn = null;

  /**
   * @since 1.0.0
   */
  #[\Symfony\Component\Validator\Constraints\Date]
  public ?string $endsOn = null;
}
