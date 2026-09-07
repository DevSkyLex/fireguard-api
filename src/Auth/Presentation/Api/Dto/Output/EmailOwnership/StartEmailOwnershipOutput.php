<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\EmailOwnership;

use Auth\Presentation\Api\Operation\EmailOwnershipOperations;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Output StartEmailOwnershipOutput.
 *
 * @category Output
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartEmailOwnershipOutput
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param string $challengeToken single-use email challenge token
   * @param int $canResendIn seconds until resend
   */
  public function __construct(
    #[Groups([EmailOwnershipOperations::READ])]
    public string $challengeToken,
    #[Groups([EmailOwnershipOperations::READ])]
    public int $canResendIn,
  ) {
  }
  // #endregion
}
