<?php

declare(strict_types=1);

namespace Onboarding\Infrastructure\Adapter\Idempotency;

use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

/**
 * Revalidates the current caller and original returned workspace before opening a saved flow.
 *
 * @category Adapter
 */
final readonly class OnboardingHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  public function __construct(
    private OrdinaryHttpReplayAuthorizationPort $authorizer,
    private ?FreshOrganizationAuthorizationPort $organizations = null,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (OnboardingHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($operationName === $operation->name) {
        return true;
      }
    }

    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    $this->assertSupported($request);

    return $this->authorizer->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $this->assertSupported($request);
    $organizationId = $storedProof?->metadata['organizationId'] ?? null;
    if (null !== $organizationId) {
      if ('' === $organizationId
        || 'organization' !== ($storedProof?->metadata['resourceKind'] ?? null)
        || $organizationId !== ($storedProof?->metadata['resourceId'] ?? null)
        || null === $this->organizations
        || !$this->organizations->isMemberOf($request->principalId, $organizationId)) {
        throw new IdempotencyDeniedException('authorization_unavailable');
      }
    } elseif (null !== $storedProof && (isset($storedProof->metadata['resourceId']) || isset($storedProof->metadata['resourceKind']))) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }

    return $this->authorizer->authorize($request, $storedProof, $organizationId);
  }

  private function assertSupported(HttpIdempotencyAuthorizationRequest $request): void
  {
    if (!$this->supports($request->operation->name)) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }
  }
}
