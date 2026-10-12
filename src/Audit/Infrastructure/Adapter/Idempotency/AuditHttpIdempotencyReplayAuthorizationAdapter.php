<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Adapter\Idempotency;

use Authorization\Application\Port\Inbound\AuthorizationPort;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

/**
 * Rechecks current platform permission, account and trusted tenant before CSV decode.
 *
 * @category Adapter
 */
final readonly class AuditHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  /**
   * @param OrdinaryHttpReplayAuthorizationPort $authorization current caller and signed original requirements
   * @param AuthorizationPort $platform fresh platform audit export permission
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $authorization, private AuthorizationPort $platform)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function supports(string $operationName): bool
  {
    return 'audit_events_export' === $operationName;
  }

  /**
   * {@inheritDoc}
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    $this->assertOperation($request);

    return $this->authorization->scope($request);
  }

  /**
   * {@inheritDoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $this->assertOperation($request);

    return $this->authorization->authorize($request, $storedProof, null);
  }

  /**
   * @param HttpIdempotencyAuthorizationRequest $request declared route facts
   */
  private function assertOperation(HttpIdempotencyAuthorizationRequest $request): void
  {
    if (!$this->supports($request->operation->name) || !$this->platform->hasPermission($request->principalId, 'audit.export')) {
      throw new IdempotencyDeniedException('access_denied');
    }
  }
}
