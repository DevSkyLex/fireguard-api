<?php

declare(strict_types=1);

namespace Messaging\Application\Service;

/**
 * Class MessagingUploadAttempt
 *
 * Tracks only the storage key written by one command invocation across rollback boundaries.
 * It is created per invocation and is never retained by a worker service.
 *
 * @category Application Service
 */
final class MessagingUploadAttempt
{
  // #region Properties
  /** Property storagePath
   *
   * Attempt-owned key, populated immediately before the write is attempted.
   */
  private ?string $storagePath = null;
  // #endregion

  // #region Methods
  /**
   * Method mark
   *
   * Records the key without observing another attempt's file.
   *
   * @access public
   *
   * @param string $storagePath attempt-owned key
   *
   * @return void
   */
  public function mark(string $storagePath): void
  {
    $this->storagePath = $storagePath;
  }

  /**
   * Method storagePath
   *
   * Returns null when validation failed before a storage write.
   *
   * @access public
   *
   * @return ?string key eligible for compensation after confirmed rollback
   */
  public function storagePath(): ?string
  {
    return $this->storagePath;
  }
  // #endregion
}
