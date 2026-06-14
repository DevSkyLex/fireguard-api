<?php

declare(strict_types=1);

namespace Intervention\Domain\Service;

use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Domain\ValueObject\InterventionStatus;

use function in_array;

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
   * @param string $changeStatus the change status value
   */
  public function assertCanChangeStatus(InterventionStatus $interventionStatus, string $changeStatus): void
  {
    if (InterventionStatus::SUBMITTED === $interventionStatus && 'rejected' === $changeStatus) {
      return;
    }
    $this->assertFieldWorkActive($interventionStatus);
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
