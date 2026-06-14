<?php

declare(strict_types=1);

namespace Mission\Domain\Service;

use Mission\Domain\Exception\MissionConflictException;
use Mission\Domain\ValueObject\MissionStatus;

use function in_array;

/**
 * Service MissionChangePolicy.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MissionChangePolicy
{
  /**
   * Method assertCanCreate.
   *
   * Executes the assert can create operation.
   *
   * @since 1.0.0
   *
   * @param MissionStatus $missionStatus the mission status value
   */
  public function assertCanCreate(MissionStatus $missionStatus): void
  {
    $this->assertFieldWorkActive($missionStatus);
  }

  /**
   * Method assertCanEditPatch.
   *
   * Executes the assert can edit patch operation.
   *
   * @since 1.0.0
   *
   * @param MissionStatus $missionStatus the mission status value
   */
  public function assertCanEditPatch(MissionStatus $missionStatus): void
  {
    $this->assertFieldWorkActive($missionStatus);
  }

  /**
   * Method assertCanDelete.
   *
   * Executes the assert can delete operation.
   *
   * @since 1.0.0
   *
   * @param MissionStatus $missionStatus the mission status value
   */
  public function assertCanDelete(MissionStatus $missionStatus): void
  {
    $this->assertFieldWorkActive($missionStatus);
  }

  /**
   * Method assertCanChangeStatus.
   *
   * Executes the assert can change status operation.
   *
   * @since 1.0.0
   *
   * @param MissionStatus $missionStatus the mission status value
   * @param string $changeStatus the change status value
   */
  public function assertCanChangeStatus(MissionStatus $missionStatus, string $changeStatus): void
  {
    if (MissionStatus::SUBMITTED === $missionStatus && 'rejected' === $changeStatus) {
      return;
    }
    $this->assertFieldWorkActive($missionStatus);
  }

  /**
   * Method assertFieldWorkActive.
   *
   * Executes the assert field work active operation.
   *
   * @since 1.0.0
   *
   * @param MissionStatus $missionStatus the mission status value
   */
  private function assertFieldWorkActive(MissionStatus $missionStatus): void
  {
    if (!in_array($missionStatus, [MissionStatus::IN_PROGRESS, MissionStatus::CHANGES_REQUESTED], true)) {
      throw new MissionConflictException('Proposed changes are editable only while field work is active.');
    }
  }
}
