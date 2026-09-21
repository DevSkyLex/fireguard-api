<?php

declare(strict_types=1);

namespace Import\Application\Exception;

use RuntimeException;

/** A different worker owns this import, or this worker's lease has expired. */
final class ImportLeaseUnavailable extends RuntimeException
{
  public function __construct()
  {
    parent::__construct('The import is currently reserved by another worker. Retry its existing identifier.');
  }
}
