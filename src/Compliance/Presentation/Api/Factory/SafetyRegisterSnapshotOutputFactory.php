<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Factory;

use Compliance\Application\Contract\SafetyRegisterSnapshotView;
use Compliance\Presentation\Api\Dto\Output\Snapshot\SafetyRegisterSnapshotOutput;

/**
 * Factory SafetyRegisterSnapshotOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SafetyRegisterSnapshotOutputFactory
{
  // #region Methods
  /**
   * Method fromView.
   *
   * @since 1.0.0
   *
   * @param SafetyRegisterSnapshotView $view the snapshot read-model row
   *
   * @return SafetyRegisterSnapshotOutput the output DTO
   */
  public function fromView(SafetyRegisterSnapshotView $view): SafetyRegisterSnapshotOutput
  {
    $output = new SafetyRegisterSnapshotOutput();
    $output->id = $view->id;
    $output->organizationId = $view->organizationId;
    $output->facilityId = $view->facilityId;
    $output->scope = $view->scope;
    $output->generatedAt = $view->generatedAt;
    $output->generatedByUserId = $view->generatedByUserId;
    $output->contentHash = $view->contentHash;
    $output->sizeBytes = $view->sizeBytes;
    $output->createdAt = $view->createdAt;

    return $output;
  }
  // #endregion
}
