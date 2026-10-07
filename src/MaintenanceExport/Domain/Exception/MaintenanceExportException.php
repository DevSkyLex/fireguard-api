<?php

declare(strict_types=1);

namespace MaintenanceExport\Domain\Exception;

use DomainException;

/**
 * Class MaintenanceExportException
 *
 * Identifies export failures without carrying HTTP or confidential source data.
 *
 * @category Exception
 */
final class MaintenanceExportException extends DomainException
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @param string $reason stable failure reason
   * @param string $message safe explanation
   */
  private function __construct(public readonly string $reason, string $message)
  {
    parent::__construct($message);
  }
  // #endregion

  // #region Methods
  /**
   * Method invalid
   *
   * @return self invalid declaration
   */
  public static function invalid(string $message): self
  {
    return new self('invalid', $message);
  }

  /**
   * Method notFound
   *
   * @return self unavailable scoped resource
   */
  public static function notFound(): self
  {
    return new self('not_found', 'Export resource unavailable.');
  }

  /**
   * Method denied
   *
   * @return self missing applicable permission
   */
  public static function denied(): self
  {
    return new self('denied', 'Export permission required.');
  }

  /**
   * Method conflict
   *
   * @return self incompatible durable declaration
   */
  public static function conflict(string $message): self
  {
    return new self('conflict', $message);
  }

  /**
   * Method snapshotMissing
   *
   * @return self missing historical publication facts
   */
  public static function snapshotMissing(): self
  {
    return new self('snapshot_missing', 'A preserved publication snapshot is required.');
  }

  /**
   * Method revisionRequired
   *
   * @return self absent revision
   */
  public static function revisionRequired(): self
  {
    return new self('revision_required', 'If-Match is required.');
  }

  /**
   * Method stale
   *
   * @return self stale revision
   */
  public static function stale(): self
  {
    return new self('stale', 'The export revision has changed.');
  }
  // #endregion
}
