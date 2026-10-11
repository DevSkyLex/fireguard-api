<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

/** Class AttachmentUploadAttempt
 *
 * Tracks the bytes owned by one transaction attempt across the callback boundary.
 *
 * @category Service
 */
final class AttachmentUploadAttempt
{
  // #region Properties
  /** Property written
   *
   * Whether writing this attempt's unique key has started.
   */
  public bool $written = false;
  // #endregion
}
