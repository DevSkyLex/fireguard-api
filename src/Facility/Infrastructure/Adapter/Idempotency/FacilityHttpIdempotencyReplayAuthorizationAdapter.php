<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Intervention\Application\Port\Inbound\FreshInterventionScopePort;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Contract\Http\{HttpIdempotencyAuthorizationRequest, ResourceIriParser};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

use function in_array;
use function is_array;
use function is_string;
use function str_starts_with;

/** Rechecks current Facility-owned scope and original grants before any saved application result is opened. @category Adapter */
final readonly class FacilityHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  public function __construct(private Connection $connection, private OrdinaryHttpReplayAuthorizationPort $ordinary, private FreshOrganizationAuthorizationPort $authorization, private FreshInterventionScopePort $interventions, private ?HttpIdempotencyAuthorizationEvidencePort $evidence = null)
  {
  }

  public function supports(string $operation): bool
  {
    foreach (FacilityHttpIdempotencyOperationContributionAdapter::definitions() as $definition) {
      if ($definition->name === $operation) {
        return true;
      }
    }

    return false;
  }

  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    return $this->ordinary->scope($request);
  }

  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    if (!$this->supports($request->operation->name)) {
      $this->deny();
    }
    $row = $this->target($request, $storedProof);
    $organization = $this->value($row, 'organization_id') ?? $this->route($request, 'organizationId') ?? $this->bodyId($request, 'organization', 'organizations');
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    $organization ??= $facts['organizationId'] ?? null;
    if (null === $organization) {
      $this->deny();
    }
    if ('facility_export' === $request->operation->name) {
      $this->requirePermissions($request, $organization, ['organization.facilities.read']);

      return $facts;
    }
    if ('canonical_facility_patch' === $request->operation->name) {
      $this->requirePermissions($request, $organization, ['organization.facilities.read']);
    }
    if (null !== $storedProof) {
      $this->assertStoredInterventionAccess($request, $row, $organization, $facts);

      return $facts;
    }

    return $this->authorizeMutation($request, $row, $organization, $facts);
  }

  /**
   * Method assertStoredInterventionAccess
   *
   * Retains the original facility intervention scope and rechecks current execution access.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the verified current caller
   * @param array<string, mixed>|null $row current facility scope, absent after deletion
   * @param string $organization the freshly authorized owning organization
   * @param array<string, string> $facts the authenticated original scope and grants
   *
   * @return void
   */
  private function assertStoredInterventionAccess(HttpIdempotencyAuthorizationRequest $request, ?array $row, string $organization, array $facts): void
  {
    $currentIntervention = $this->value($row, 'intervention_id');
    if (null !== $currentIntervention && isset($facts['interventionId']) && $currentIntervention !== $facts['interventionId']) {
      $this->deny();
    }
    $intervention = $currentIntervention ?? $facts['interventionId'] ?? null;
    if (null !== $intervention && ($facts['interventionPermission'] ?? '') === 'organization.interventions.execute') {
      $this->interventions->assertExecutionAccess($organization, $intervention, $request->principalId);
    }
  }

  /**
   * Method authorizeMutation
   *
   * Captures the original hierarchy-write grant, including canonical detail read and intervention requirements.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the verified logical mutation
   * @param array<string, mixed>|null $row the current facility scope
   * @param string $organization the authorized owning organization
   * @param array<string, string> $facts the common caller and organization evidence
   *
   * @return array<string, string> complete evidence for the first execution
   */
  private function authorizeMutation(HttpIdempotencyAuthorizationRequest $request, ?array $row, string $organization, array $facts): array
  {
    $permission = 'organization.facilities.write';
    $intervention = $this->bodyId($request, 'intervention', 'interventions');
    if (null === $intervention && ('draft' === $this->value($row, 'record_status') || null === $row)) {
      $intervention = $this->value($row, 'intervention_id');
    }
    if (null !== $intervention && in_array($request->operation->name, ['_api_/facilities_post', 'canonical_facility_put', 'facility_create', 'canonical_facility_patch', 'canonical_facility_delete'], true)) {
      $permission = $this->interventions->mutationPermission($intervention, $request->principalId, $organization);
      $facts['interventionId'] = $intervention;
      $facts['interventionPermission'] = $permission;
    }
    $permissions = 'canonical_facility_patch' === $request->operation->name ? [$permission, 'organization.facilities.read'] : [$permission];
    $this->requirePermissions($request, $organization, $permissions);

    return $facts;
  }

  /**
   * @return array<string, mixed>|null
   */
  private function target(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof): ?array
  {
    $kind = $request->operation->resourceKind;
    $saved = $proof?->metadata['resourceId'] ?? null;

    return match ($kind) {
      'facility_metadata_field', 'facility_model' => $this->auxiliaryTarget($request, $saved),
      'facility_attachment' => $this->attachmentTarget($request, $saved),
      default => $this->facilityTarget($saved ?? $this->route($request, 'facilityId') ?? $this->route($request, 'id')),
    };
  }

  /**
   * Method auxiliaryTarget
   *
   * Reads an existing metadata field or model owner, or resolves the building for a new upload.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the verified model or metadata route
   * @param string|null $saved the original created resource identifier
   *
   * @return array<string, mixed>|null current owner scope, absent when the resource is gone
   */
  private function auxiliaryTarget(HttpIdempotencyAuthorizationRequest $request, ?string $saved): ?array
  {
    $id = $saved ?? $this->route($request, 'id');
    if (null !== $id) {
      $sql = 'facility_model' === $request->operation->resourceKind ? 'SELECT organization_id FROM facility_models WHERE id = :id' : 'SELECT organization_id FROM facility_metadata_fields WHERE id = :id';
      $row = $this->connection->fetchAssociative($sql, ['id' => $id]);

      return false === $row ? null : $row;
    }

    return $this->facilityTarget($this->route($request, 'buildingId'));
  }

  /**
   * Method attachmentTarget
   *
   * Resolves the actual attachment owner and fails closed when the route names another facility.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the verified attachment route
   * @param string|null $saved the original attachment identifier when replaying
   *
   * @return array<string, mixed>|null current facility scope, absent after deletion
   */
  private function attachmentTarget(HttpIdempotencyAuthorizationRequest $request, ?string $saved): ?array
  {
    $attachment = $saved ?? $this->route($request, 'id');
    if (null !== $attachment) {
      $row = $this->connection->fetchAssociative('SELECT f.id, f.organization_id, f.intervention_id, f.record_status FROM facility_attachments a JOIN facilities f ON f.id = a.facility_id WHERE a.id = :id', ['id' => $attachment]);
      if (false !== $row) {
        $parent = $this->route($request, 'facilityId');
        if (null !== $parent && $this->value($row, 'id') !== $parent) {
          $this->deny();
        }

        return $row;
      }
    }

    return $this->facilityTarget($this->route($request, 'facilityId'));
  }

  /**
   * Method facilityTarget
   *
   * Reads the current main-database owner for a persisted or offline facility identifier.
   *
   * @access private
   *
   * @param string|null $id the trusted route or saved facility identifier
   *
   * @return array<string, mixed>|null current facility scope, absent after deletion
   */
  private function facilityTarget(?string $id): ?array
  {
    if (null === $id) {
      return null;
    }
    $row = $this->connection->fetchAssociative('SELECT id, organization_id, intervention_id, record_status FROM facilities WHERE id = :id OR client_id = :id', ['id' => $id]);

    return false === $row ? null : $row;
  }

  /**
   * @param list<string> $permissions
   */
  private function requirePermissions(HttpIdempotencyAuthorizationRequest $request, string $organization, array $permissions): void
  {
    if (!$this->authorization->isMemberOf($request->principalId, $organization)) {
      $this->deny();
    }
    foreach ($permissions as $permission) {
      if (!$this->authorization->hasPermission($request->principalId, $organization, $permission)) {
        $this->deny();
      }
    }
    $this->evidence?->record($request->principalId, $organization, $permissions);
  }

  private function route(HttpIdempotencyAuthorizationRequest $request, string $key): ?string
  {
    $value = $request->uriVariables[$key] ?? null;

    return is_string($value) && '' !== $value ? $value : null;
  }

  private function bodyId(HttpIdempotencyAuthorizationRequest $request, string $key, string $collection): ?string
  {
    $body = $request->canonicalInput['body'] ?? null;
    if (!is_array($body)) {
      return null;
    }
    $fields = $body['fields'] ?? (is_array($body['value'] ?? null) ? $body['value']['fields'] ?? null : null);
    $value = is_array($fields) ? $fields[$key] ?? null : null;

    if (!is_string($value) || '' === $value) {
      return null;
    }

    return str_starts_with($value, '/api/') ? ResourceIriParser::id($value, $collection) : $value;
  }

  /**
   * @param array<string, mixed>|null $row
   */
  private function value(?array $row, string $key): ?string
  {
    $value = $row[$key] ?? null;

    return is_string($value) && '' !== $value ? $value : null;
  }

  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
}
