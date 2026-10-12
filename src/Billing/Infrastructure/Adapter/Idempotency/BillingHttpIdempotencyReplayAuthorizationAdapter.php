<?php

declare(strict_types=1);

namespace Billing\Infrastructure\Adapter\Idempotency;

use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

use function in_array;
use function is_string;

/**
 * Class BillingHttpIdempotencyReplayAuthorizationAdapter
 *
 * Rechecks the current billing administrator before decrypting a hosted session URL or replaying a subscription response.
 *
 * @category Adapter
 */
final readonly class BillingHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  /**
   * Method __construct
   *
   * @param OrdinaryHttpReplayAuthorizationPort $ordinary current actor and signed original requirements
   * @param FreshOrganizationAuthorizationPort $organizations uncached membership and grants
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $ordinary, private FreshOrganizationAuthorizationPort $organizations)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    return in_array($operationName, ['startBillingCheckout', 'startBillingPortal', 'cancelOrganizationSubscription', 'resumeOrganizationSubscription'], true);
  }

  /**
   * {@inheritdoc}
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    return $this->ordinary->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $organization = $request->uriVariables['organizationId'] ?? null;
    if (!$this->supports($request->operation->name) || !is_string($organization) || '' === $organization
      || !$this->organizations->hasPermission($request->principalId, $organization, 'organization.settings.write')) {
      throw new IdempotencyDeniedException('access_denied');
    }

    return $this->ordinary->authorize($request, $storedProof, $organization);
  }
}
