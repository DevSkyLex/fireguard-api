<?php

declare(strict_types=1);

namespace Approval\Application\Contract\Action;

use InvalidArgumentException;

use function preg_match;

/**
 * Class ApprovalActionKey
 *
 * Represents an open action identifier within the persisted 64-character boundary.
 * Representation validation does not depend on installed contributors.
 *
 * @category Contract
 */
final readonly class ApprovalActionKey
{
  // #region Constructor
  /**
   * Constructor
   *
   * @access public
   *
   * @param string $value the persisted action identifier
   */
  public function __construct(public string $value)
  {
    if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $value)) {
      throw new InvalidArgumentException('Invalid approval action key.');
    }
  }
  // #endregion
}
