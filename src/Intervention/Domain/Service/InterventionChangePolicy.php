<?php

declare(strict_types=1);

namespace Intervention\Domain\Service;

use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Domain\ValueObject\{InterventionChangeStatus, InterventionStatus};

use function in_array;
use function sprintf;

/**
 * Service InterventionChangePolicy.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionChangePolicy
{
  /**
   * Method assertCanCreate.
   *
   * Executes the assert can create operation.
   *
   * @since 1.0.0
   *
   * @param InterventionStatus $interventionStatus the intervention status value
   */
  public function assertCanCreate(InterventionStatus $interventionStatus): void
  {
    $this->assertFieldWorkActive($interventionStatus);
  }

  /**
   * Method assertCanEditPatch.
   *
   * Executes the assert can edit patch operation.
   *
   * @since 1.0.0
   *
   * @param InterventionStatus $interventionStatus the intervention status value
   */
  public function assertCanEditPatch(InterventionStatus $interventionStatus): void
  {
    $this->assertFieldWorkActive($interventionStatus);
  }

  /**
   * Method assertCanDelete.
   *
   * Executes the assert can delete operation.
   *
   * @since 1.0.0
   *
   * @param InterventionStatus $interventionStatus the intervention status value
   */
  public function assertCanDelete(InterventionStatus $interventionStatus): void
  {
    $this->assertFieldWorkActive($interventionStatus);
  }

  /**
   * Method assertCanChangeStatus.
   *
   * Executes the assert can change status operation.
   *
   * @since 1.0.0
   *
   * @param InterventionStatus $interventionStatus the intervention status value
   * @param InterventionChangeStatus $changeStatus the change status value
   */
  public function assertCanChangeStatus(InterventionStatus $interventionStatus, InterventionChangeStatus $changeStatus): void
  {
    if (InterventionStatus::SUBMITTED === $interventionStatus && InterventionChangeStatus::REJECTED === $changeStatus) {
      return;
    }
    $this->assertFieldWorkActive($interventionStatus);
  }

  /**
   * Method assertTransitionAllowed.
   *
   * Enforces the change-status state machine: `proposed` moves to `rejected`
   * or `applied`, `rejected` can be reopened to `proposed`, and `applied` is
   * terminal — it is reached only through the publication path, never from
   * user input.
   *
   * @since 1.1.0
   *
   * @param InterventionChangeStatus $from the from value
   * @param InterventionChangeStatus $to the to value
   */
  public function assertTransitionAllowed(InterventionChangeStatus $from, InterventionChangeStatus $to): void
  {
    if ($from === $to) {
      return;
    }

    if (!in_array($to, $this->allowedFrom($from), true)) {
      throw new InterventionConflictException(sprintf('Intervention change cannot transition from %s to %s.', $from->value, $to->value));
    }
  }

  /**
   * Method allowedFrom.
   *
   * Lists the state-machine-legal next statuses from the given change
   * status. `applied` is never reachable through this table from user input
   * since the presentation layer's `Assert\Choice` only ever offers
   * `proposed`/`rejected`; the publication path sets it directly.
   *
   * @since 1.1.0
   *
   * @param InterventionChangeStatus $from the from value
   *
   * @return list<InterventionChangeStatus> the allowed next statuses
   */
  public function allowedFrom(InterventionChangeStatus $from): array
  {
    return match ($from) {
      InterventionChangeStatus::PROPOSED => [InterventionChangeStatus::REJECTED, InterventionChangeStatus::APPLIED],
      InterventionChangeStatus::REJECTED => [InterventionChangeStatus::PROPOSED],
      InterventionChangeStatus::APPLIED => [],
    };
  }

  /**
   * Method assertFieldWorkActive.
   *
   * Executes the assert field work active operation.
   *
   * @since 1.0.0
   *
   * @param InterventionStatus $interventionStatus the intervention status value
   */
  private function assertFieldWorkActive(InterventionStatus $interventionStatus): void
  {
    if (!in_array($interventionStatus, [InterventionStatus::IN_PROGRESS, InterventionStatus::CHANGES_REQUESTED], true)) {
      throw new InterventionConflictException('Proposed changes are editable only while field work is active.');
    }
  }
}
