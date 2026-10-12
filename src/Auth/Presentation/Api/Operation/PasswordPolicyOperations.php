<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Operation;

/**
 * Operations PasswordPolicyOperations.
 *
 * @category Operations
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PasswordPolicyOperations
{
  /**
   * @var string public policy read operation
   */
  public const string GET = 'auth_password_policy';
}
