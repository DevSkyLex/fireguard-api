<?php

declare(strict_types=1);

namespace User\Application\Port\Outbound;

use SensitiveParameter;
use User\Application\Contract\Password\PasswordPolicyDescription;

/**
 * Port PasswordPolicyPort.
 *
 * Publishes project password requirements and the authoritative validation.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface PasswordPolicyPort
{
  /**
   * Method describe.
   *
   * @since 1.0.0
   *
   * @return PasswordPolicyDescription public requirements
   */
  public function describe(): PasswordPolicyDescription;

  /**
   * Method violations.
   *
   * @since 1.0.0
   *
   * @param string $password proposed plaintext
   *
   * @return list<string> stable violated requirement identifiers
   */
  public function violations(#[SensitiveParameter] string $password): array;

  /**
   * Method assertAcceptable.
   *
   * @since 1.0.0
   *
   * @param string $password proposed plaintext
   *
   * @return void
   */
  public function assertAcceptable(#[SensitiveParameter] string $password): void;
}
