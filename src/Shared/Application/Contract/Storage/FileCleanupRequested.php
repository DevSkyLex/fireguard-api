<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Storage;

use InvalidArgumentException;

use function in_array;
use function trim;

/**
 * Class FileCleanupRequested
 *
 * Carries an obsolete storage key across durable retries without embedding storage adapter types.
 *
 * @category Application Contract
 */
final readonly class FileCleanupRequested
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the storage key selected by the owning media command.
   *
   * @access public
   *
   * @param string $storagePath the nonempty obsolete key
   * @param string $owningDatabase the validated main or auth database whose mutation and cleanup receipt own the request
   *
   * @return void
   */
  public function __construct(public string $storagePath, public string $owningDatabase = 'main')
  {
    if ('' === trim($storagePath)) {
      throw new InvalidArgumentException('A file cleanup request requires a storage key.');
    }
    if (!in_array($owningDatabase, ['main', 'auth'], true)) {
      throw new InvalidArgumentException('File cleanup requires an owning database.');
    }
  }
  // #endregion
}
