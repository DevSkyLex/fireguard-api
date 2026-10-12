<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

use DateTimeImmutable;

/**
 * Contract FederatedBrowserBinding
 *
 * Publishes verified browser identity and its fixed server-issued deadline without exposing the cookie secret.
 *
 * @category Contract
 */
final readonly class FederatedBrowserBinding
{
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $bindingHash verified browser ticket hash
   * @param DateTimeImmutable $expiresAt fixed original browser deadline
   *
   * @return void
   */
  public function __construct(public string $bindingHash, public DateTimeImmutable $expiresAt)
  {
  }
}
