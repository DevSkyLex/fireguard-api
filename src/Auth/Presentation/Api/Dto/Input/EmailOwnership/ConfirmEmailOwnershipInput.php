<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Input\EmailOwnership;

use Auth\Presentation\Api\Operation\EmailOwnershipOperations;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input ConfirmEmailOwnershipInput.
 *
 * @category Input
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ConfirmEmailOwnershipInput
{
  // #region Properties
  /**
   * @since 1.0.0
   */
  #[Groups([EmailOwnershipOperations::WRITE])]
  #[Assert\NotBlank]
  #[Assert\Regex(pattern: '/^[a-f0-9]{64}$/D')]
  public string $challengeToken = '';

  /**
   * @since 1.0.0
   */
  #[Groups([EmailOwnershipOperations::WRITE])]
  #[Assert\NotBlank]
  #[Assert\Regex(pattern: '/^[0-9]{4,10}$/D')]
  public string $code = '';
  // #endregion
}
