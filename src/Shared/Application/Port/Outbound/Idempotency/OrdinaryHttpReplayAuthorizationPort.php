<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyProof, IdempotencyScope};

/**
 * Interface OrdinaryHttpReplayAuthorizationPort
 *
 * Publishes common current caller and signed requirement checks; resource owners supply only their freshly resolved scalar organization.
 *
 * @category Port
 */
interface OrdinaryHttpReplayAuthorizationPort
{
  // #region Methods
  /**
   * Method scope
   *
   * @access public
   *
   * @param HttpIdempotencyAuthorizationRequest $request verified current route identity
   *
   * @return IdempotencyScope global route scope or organization-prefixed route scope
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope;

  /**
   * Method authorize
   *
   * @access public
   *
   * @param HttpIdempotencyAuthorizationRequest $request verified current route identity
   * @param IdempotencyProof|null $storedProof MAC-verified first-completion evidence
   * @param string|null $currentOrganizationId fresh owner lookup result, null when the original target is gone or the route has no organization
   *
   * @return array<string, string> verified nonsecret facts; denial happens before body decoding
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, ?string $currentOrganizationId): array;
  // #endregion
}
