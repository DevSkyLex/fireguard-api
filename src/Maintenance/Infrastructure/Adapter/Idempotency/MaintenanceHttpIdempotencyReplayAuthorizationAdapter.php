<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Equipment\Application\Port\Inbound\FreshEquipmentScopePort;
use Facility\Application\Port\Inbound\FreshFacilityScopePort;
use Intervention\Application\Port\Inbound\FreshInterventionScopePort;
use Maintenance\Domain\Exception\MaintenanceNotFoundException;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Contract\Http\{HttpIdempotencyAuthorizationRequest, ResourceIriParser};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

use function in_array;
use function is_array;
use function is_string;

/**
 * Class MaintenanceHttpIdempotencyReplayAuthorizationAdapter
 *
 * Rechecks current own plans/schedules and public equipment, facility and generated intervention scope before receipt disclosure.
 *
 * @category Adapter
 */
final readonly class MaintenanceHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constructor
  /**
   * @param OrdinaryHttpReplayAuthorizationPort $authorizer current account and captured requirements
   * @param Connection $connection explicitly selected main connection
   * @param FreshOrganizationAuthorizationPort $organizations uncached current workspace authority
   * @param FreshEquipmentScopePort $equipment published current equipment ownership
   * @param FreshFacilityScopePort $facilities published current facility ownership
   * @param FreshInterventionScopePort $interventions published current generated intervention ownership
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $authorizer, private Connection $connection, private FreshOrganizationAuthorizationPort $organizations, private FreshEquipmentScopePort $equipment, private FreshFacilityScopePort $facilities, private FreshInterventionScopePort $interventions)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (MaintenanceHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
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
    $name = $request->operation->name;
    $body = $request->canonicalInput['body'] ?? [];
    $body = is_array($body) ? $body : [];
    $value = $body['value'] ?? [];
    $value = is_array($value) ? $value : [];
    $fields = $value['fields'] ?? [];
    $fields = is_array($fields) ? $fields : [];
    $organizationId = $request->uriVariables['organizationId'] ?? $fields['organizationId'] ?? null;
    $facilityId = $fields['facilityId'] ?? null;
    if ('maintenance_schedule_export' === $name) {
      $query = $request->canonicalInput['query'] ?? [];
      $query = is_array($query) ? $query : [];
      $organization = $query['organization'] ?? null;
      $organizationId = is_string($organization) && '' !== $organization ? ResourceIriParser::id($organization, 'organizations') : null;
      $facility = $query['facility'] ?? null;
      $facilityId = is_string($facility) && '' !== $facility ? ResourceIriParser::id($facility, 'facilities') : null;
    }
    $id = $request->uriVariables['id'] ?? null;
    $equipmentId = $fields['equipmentId'] ?? null;
    if ('_api_/maintenance/schedules/{id}_patch' === $name || null !== $id) {
      if (!is_string($id)) {
        throw new IdempotencyDeniedException('access_denied');
      }
      $table = '_api_/maintenance/schedules/{id}_patch' === $name ? 'maintenance_schedules' : 'maintenance_plans';
      $row = $this->connection->fetchAssociative('SELECT organization_id, equipment_id FROM ' . $table . ' WHERE id = :id', ['id' => $id]);
      if (false === $row || !is_string($row['organization_id'] ?? null) || (null !== $organizationId && $row['organization_id'] !== $organizationId)) {
        throw MaintenanceNotFoundException::withId($id);
      }
      $organizationId = $row['organization_id'];
      $equipmentId = $row['equipment_id'] ?? null;
    }
    if (!is_string($organizationId) || '' === $organizationId || !$this->organizations->isMemberOf($request->principalId, $organizationId)) {
      throw new IdempotencyDeniedException('access_denied');
    }
    $permissions = 'maintenance_schedule_export' === $name ? ['organization.maintenance.read'] : (in_array($name, ['_api_/maintenance/campaigns_post', 'maintenance_plan_generate'], true)
      ? ['organization.maintenance.manage', 'organization.interventions.plan']
      : ['organization.maintenance.manage']);
    foreach ($permissions as $permission) {
      if (!$this->organizations->hasPermission($request->principalId, $organizationId, $permission)) {
        throw new IdempotencyDeniedException('access_denied');
      }
    }
    if (null !== $equipmentId && (!is_string($equipmentId) || $this->equipment->organizationId($equipmentId) !== $organizationId)) {
      throw new IdempotencyDeniedException('access_denied');
    }
    if (null !== $facilityId && (!is_string($facilityId) || $this->facilities->organizationId($facilityId) !== $organizationId)) {
      throw new IdempotencyDeniedException('access_denied');
    }
    if (null !== $storedProof && isset($storedProof->metadata['resourceId'])) {
      $resourceId = $storedProof->metadata['resourceId'];
      if (($storedProof->metadata['resourceKind'] ?? null) !== $request->operation->resourceKind) {
        throw new IdempotencyDeniedException('access_denied');
      }
      if ('intervention' === $request->operation->resourceKind) {
        $resourceOrganization = $this->interventions->organizationId($resourceId);
      } else {
        $table = 'maintenance_schedule' === $request->operation->resourceKind ? 'maintenance_schedules' : 'maintenance_plans';
        $resourceOrganization = $this->connection->fetchOne('SELECT organization_id FROM ' . $table . ' WHERE id = :id', ['id' => $resourceId]);
      }
      if ($resourceOrganization !== $organizationId) {
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
