<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\EmailOwnership;

use Auth\Presentation\Api\Operation\EmailOwnershipOperations;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Output EmailOwnershipOutput.
 *
 * @category Output
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EmailOwnershipOutput
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param bool $verified current mailbox ownership proof
   */
  public function __construct(
    #[Groups([EmailOwnershipOperations::READ])]
    public bool $verified,
  ) {
  }
  // #endregion
}
