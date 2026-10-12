<?php

declare(strict_types=1);

namespace Audit\Application\Contract;

use RuntimeException;

/**
 * Rejects a CSV exceeding the bounded application receipt size before any commit.
 *
 * @category Exception
 */
final class AuditExportSizeLimitException extends RuntimeException
{
  public function __construct()
  {
    parent::__construct('The audit CSV exceeds the 64 MiB export limit. Narrow the filters and try again.');
  }
}
