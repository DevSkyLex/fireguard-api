<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Command\Snapshot\CreateSafetyRegisterSnapshot;

use Compliance\Application\Contract\SafetyRegisterSnapshotView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreateSafetyRegisterSnapshotResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateSafetyRegisterSnapshotResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param SafetyRegisterSnapshotView $snapshot the archived snapshot metadata
   */
  public function __construct(
    public SafetyRegisterSnapshotView $snapshot,
  ) {
  }
  // #endregion
}
