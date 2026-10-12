<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyProof, IdempotencyScope};

/**
 * Interface HttpIdempotencyReplayAuthorizationPort
 *
 * An operation owner rechecks current principal, context and original authorization before any saved response is decoded.
 *
 * @category Port
 */
interface HttpIdempotencyReplayAuthorizationPort
{
  // #region Methods
  /**
   * Method supports
   *
   * @access public
   *
   * @param string $operationName exact declared owner operation
   *
   * @return bool whether this owner owns its ordinary replay policy
   */
  public function supports(string $operationName): bool;

  /**
   * Method scope
   *
   * Global resource routes leave organization null; organization-prefixed routes bind the trusted route identifier.
   *
   * @access public
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated current route facts
   *
   * @return IdempotencyScope exact receipt scope, never a bearer credential
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope;

  /**
   * Method authorize
   *
   * A stored proof must come from inspectProof after its scoped MAC verification. Removed targets retain their signed original context.
   *
   * @access public
   *
   * @param HttpIdempotencyAuthorizationRequest $request current verified caller and route
   * @param IdempotencyProof|null $storedProof authenticated first-completion facts or null before a fresh execution
   *
   * @return array<string, string> verified nonsecret proof facts for the owning receipt
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array;
  // #endregion
}
