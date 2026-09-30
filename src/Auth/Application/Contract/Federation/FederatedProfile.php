<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

use Auth\Domain\ValueObject\Federation\FederatedProvider;

/**
 * Contract FederatedProfile.
 *
 * Normalized identity returned by a provider after the code exchange.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedProfile
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the identity claims returned by a federated provider after profile validation.
   *
   * @access public
   *
   * @param FederatedProvider $provider provider that authenticated the identity
   * @param string $subject provider-scoped subject identifier
   * @param string $email email address asserted by the provider
   * @param bool $emailVerified whether the provider reports the email as verified
   * @param string $firstName given name returned by the provider
   * @param string $lastName family name returned by the provider
   *
   * @return void
   */
  public function __construct(
    public FederatedProvider $provider,
    public string $subject,
    public string $email,
    public bool $emailVerified,
    public string $firstName,
    public string $lastName,
  ) {
  }
  // #endregion
}
