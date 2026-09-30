<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

use Auth\Domain\ValueObject\Federation\FederatedProvider;
use DateTimeImmutable;

/**
 * Contract FederatedFlow.
 *
 * One-time server-side state for a federated authorization-code flow.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedFlow
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Stores the server-side state required to validate and complete a federated authorization-code flow.
   *
   * @access public
   *
   * @param string $stateHash hash of the one-time OAuth state value
   * @param string $browserBindingHash hash binding the flow to its initiating browser
   * @param FederatedProvider $provider provider handling the authorization request
   * @param string $intent operation requested by the federated flow
   * @param ?string $userId account being linked, when the flow is an account-link operation
   * @param string $codeVerifier PKCE verifier needed to exchange the authorization code
   * @param string $redirectUri registered callback URI used for the flow
   * @param string $returnUrl validated application URL used after the flow
   * @param DateTimeImmutable $expiresAt deadline after which the one-time state is invalid
   *
   * @return void
   */
  public function __construct(
    public string $stateHash,
    public string $browserBindingHash,
    public FederatedProvider $provider,
    public string $intent,
    public ?string $userId,
    public string $codeVerifier,
    public string $redirectUri,
    public string $returnUrl,
    public DateTimeImmutable $expiresAt,
  ) {
  }
  // #endregion
}
