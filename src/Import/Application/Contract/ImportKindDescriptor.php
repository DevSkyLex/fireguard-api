<?php

declare(strict_types=1);

namespace Import\Application\Contract;

use InvalidArgumentException;

use function preg_match;

/**
 * Contract ImportKindDescriptor
 *
 * Publishes a contributor's stable persisted key, authorization and empty CSV template.
 *
 * @category Contract
 */
final readonly class ImportKindDescriptor
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Empty permissions and templates are rejected before the registry becomes available.
   *
   * @access public
   *
   * @param string $key persisted resource kind, limited to the existing sixteen-character column
   * @param string $readPermission organization permission for observing jobs
   * @param string $writePermission organization permission for creating and executing jobs
   * @param string $templateFilename downloadable filename
   * @param string $templateContent CSV header without example records
   *
   * @return void
   */
  public function __construct(
    public string $key,
    public string $readPermission,
    public string $writePermission,
    public string $templateFilename,
    public string $templateContent,
  ) {
    if (1 !== preg_match('/^[a-z][a-z0-9_]{0,15}$/D', $key)) {
      throw new InvalidArgumentException('Invalid import contributor key: ' . $key);
    }
    if ('' === $readPermission || '' === $writePermission || '' === $templateFilename || '' === $templateContent) {
      throw new InvalidArgumentException('Import contributors require permissions and a CSV template.');
    }
  }
  // #endregion
}
