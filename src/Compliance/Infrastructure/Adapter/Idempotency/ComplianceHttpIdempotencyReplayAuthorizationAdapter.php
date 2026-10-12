<?php

declare(strict_types=1);

namespace Compliance\Infrastructure\Adapter\Idempotency;

use Compliance\Application\Port\Outbound\ComplianceExportEntitlementPort;
use Doctrine\DBAL\Connection;
use Facility\Application\Port\Inbound\FreshFacilityScopePort;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

use function is_array;
use function is_string;

/**
 * Class ComplianceHttpIdempotencyReplayAuthorizationAdapter
 *
 * Rechecks current regulatory export permission, uncached plan entitlement and facility/snapshot ownership before revealing retained metadata or PDF bytes.
 *
 * @category Adapter
 */
final readonly class ComplianceHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constructor
  /**
   * @param OrdinaryHttpReplayAuthorizationPort $authorizer current account and signed captured authority
   * @param Connection $connection explicitly selected main connection
   * @param FreshOrganizationAuthorizationPort $organizations current uncached membership and grants
   * @param ComplianceExportEntitlementPort $entitlement explicitly bound uncached current owner entitlement
   * @param FreshFacilityScopePort $facilities current public facility scope
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $authorizer, private Connection $connection, private FreshOrganizationAuthorizationPort $organizations, private ComplianceExportEntitlementPort $entitlement, private FreshFacilityScopePort $facilities)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (ComplianceHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
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
    $organizationId = $request->uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId || !$this->organizations->isMemberOf($request->principalId, $organizationId) || !$this->organizations->hasPermission($request->principalId, $organizationId, 'organization.compliance.export') || !$this->entitlement->isExportEntitled($organizationId)) {
      throw new IdempotencyDeniedException('access_denied');
    }
    $body = $request->canonicalInput['body'] ?? [];
    $body = is_array($body) ? $body : [];
    $value = $body['value'] ?? [];
    $value = is_array($value) ? $value : [];
    $fields = $value['fields'] ?? [];
    $fields = is_array($fields) ? $fields : [];
    $facilityId = $request->uriVariables['facilityId'] ?? $fields['facilityId'] ?? null;
    if (null !== $facilityId && (!is_string($facilityId) || $this->facilities->organizationId($facilityId) !== $organizationId)) {
      throw new IdempotencyDeniedException('access_denied');
    }
    if (null !== $storedProof && isset($storedProof->metadata['resourceId'])) {
      if ('compliance_snapshot' !== ($storedProof->metadata['resourceKind'] ?? null) || $this->connection->fetchOne('SELECT organization_id FROM compliance_register_snapshots WHERE id = :id', ['id' => $storedProof->metadata['resourceId']]) !== $organizationId) {
        throw new IdempotencyDeniedException('access_denied');
      }
    }

    return $this->authorizer->authorize($request, $storedProof, $organizationId);
  }

  /**
   * @param HttpIdempotencyAuthorizationRequest $request trusted route
   */
  private function assertSupported(HttpIdempotencyAuthorizationRequest $request): void
  {
    if (!$this->supports($request->operation->name)) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }
  }
  // #endregion
}
