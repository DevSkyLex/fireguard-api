<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound\Token;

use OAuth\Application\Contract\Token\{AuthorizationGrantOutcome, AuthorizationReceiptRequest};

/**
 * Owns native authorization-code idempotency within the final auth transaction.
 *
 * @category Outbound Port
 */
interface AuthorizationReceiptPort
{
  /**
   * @param AuthorizationReceiptRequest $request current validated browser and client request
   * @param callable():AuthorizationGrantOutcome $completion locked owner grant completion
   * @param callable():bool $authorizeCurrent current verified browser/session authorization, including unknown-commit recovery
   *
   * @return AuthorizationGrantOutcome original currently authorized protocol result
   */
  public function complete(AuthorizationReceiptRequest $request, callable $completion, callable $authorizeCurrent): AuthorizationGrantOutcome;
}
