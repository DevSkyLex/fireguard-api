<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\Password\GetPasswordPolicy;

use Shared\Application\Message\ResultMessage;
use User\Application\Contract\Password\PasswordPolicyDescription;

/**
 * Result GetPasswordPolicyResult.
 *
 * @category Result
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPasswordPolicyResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param PasswordPolicyDescription $requirements the configured public requirements
   */
  public function __construct(public PasswordPolicyDescription $requirements)
  {
  }
}
