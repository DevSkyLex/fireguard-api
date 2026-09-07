<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Operation;

/**
 * PasswordSetupOperations.
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PasswordSetupOperations
{
  public const string REQUEST = 'request_initial_password';

  public const string CONFIRM = 'confirm_initial_password';
}
