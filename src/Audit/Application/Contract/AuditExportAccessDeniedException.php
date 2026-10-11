<?php

declare(strict_types=1);

namespace Audit\Application\Contract;

use RuntimeException;

/**
 * Class AuditExportAccessDeniedException
 *
 * Rejects an export before reading the ledger when the caller lacks the export permission.
 *
 * @category Exception
 */
final class AuditExportAccessDeniedException extends RuntimeException
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Preserves the existing export denial message for centralized HTTP mapping.
   *
   * @access public
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct('Insufficient permissions (requires audit.export).');
  }
  // #endregion
}
