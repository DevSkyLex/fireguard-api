<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Equipment\Application\Port\Outbound\EquipmentReportEntitlementPort;
use Intervention\Application\Port\Inbound\FreshInterventionScopePort;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Contract\Http\{HttpIdempotencyAuthorizationRequest, ResourceIriParser};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

use function in_array;
use function is_array;
use function is_string;
use function str_starts_with;

/** Rechecks current Equipment-owned scope and original grants before any saved application result is opened. @category Adapter */
final readonly class EquipmentHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  public function __construct(private Connection $connection, private OrdinaryHttpReplayAuthorizationPort $ordinary, private FreshOrganizationAuthorizationPort $authorization, private FreshInterventionScopePort $interventions, private ?HttpIdempotencyAuthorizationEvidencePort $evidence = null, private ?EquipmentReportEntitlementPort $exportEntitlement = null)
  {
  }

  public function supports(string $operation): bool
  {
    foreach (EquipmentHttpIdempotencyOperationContributionAdapter::definitions() as $definition) {
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
    if (in_array($request->operation->name, ['equipment_export', 'equipment_export_report', 'equipment_export_labels'], true)) {
      $this->requirePermissions($request, $organization, ['organization.equipment.read']);
      if ('equipment_export_report' === $request->operation->name && !$this->exportEntitlement?->isExportEntitled($organization)) {
        $this->deny();
      }

      return $facts;
    }
    if (null !== $storedProof) {
      $currentIntervention = $this->value($row, 'intervention_id');
      if (null !== $currentIntervention && isset($facts['interventionId']) && $currentIntervention !== $facts['interventionId']) {
        $this->deny();
      }
      $intervention = $this->value($row, 'intervention_id') ?? $facts['interventionId'] ?? null;
      if (null !== $intervention && ($facts['interventionPermission'] ?? '') === 'organization.interventions.execute') {
        $this->interventions->assertExecutionAccess($organization, $intervention, $request->principalId);
      }

      return $facts;
    }
    $permission = 'organization.equipment.write';
    $intervention = $this->bodyId($request, 'intervention', 'interventions');
    if (null === $intervention && ('draft' === $this->value($row, 'record_status') || null === $row)) {
      $intervention = $this->value($row, 'intervention_id');
    }
    if (null !== $intervention && (in_array($request->operation->name, ['_api_/equipment_post', 'canonical_equipment_put', 'equipment_create', 'canonical_equipment_patch', 'canonical_equipment_delete', '_api_/media_post', '_api_/media/{id}_delete'], true))) {
      $permission = $this->interventions->mutationPermission($intervention, $request->principalId, $organization);
      $facts['interventionId'] = $intervention;
      $facts['interventionPermission'] = $permission;
    }
    $this->requirePermissions($request, $organization, [$permission]);

    return $facts;
  }

  /**
   * @return array<string, mixed>|null
   */
  private function target(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof): ?array
  {
    $kind = $request->operation->resourceKind;
    $saved = $proof?->metadata['resourceId'] ?? null;
    if ('equipment_type' === $kind) {
      $code = $saved ?? $this->route($request, 'typeCode');
      $organization = $this->route($request, 'organizationId');
      if (null === $code || null === $organization) {
        return null;
      }
      $row = $this->connection->fetchAssociative('SELECT organization_id FROM equipment_type_catalog WHERE organization_id = :org AND type_code = :code', ['org' => $organization, 'code' => $code]);

      return false === $row ? null : $row;
    }
    if ('equipment_attachment' === $kind) {
      $attachment = $saved ?? $this->route($request, 'attachmentId') ?? $this->route($request, 'id');
      if (null !== $attachment) {
        $row = $this->connection->fetchAssociative('SELECT e.id, e.organization_id, e.intervention_id, e.record_status FROM equipment_attachments a JOIN equipment e ON e.id = a.equipment_id WHERE a.id = :id', ['id' => $attachment]);
        if (false !== $row) {
          $parent = $this->route($request, 'equipmentId');
          if (null !== $parent && $this->value($row, 'id') !== $parent) {
            $this->deny();
          }

          return $row;
        }
      }
      $id = $this->route($request, 'equipmentId') ?? $this->bodyId($request, 'equipment', 'equipment');
    } else {
      $id = $saved ?? $this->route($request, 'equipmentId') ?? $this->route($request, 'id');
    }
    if (null === $id) {
      return null;
    }
    $row = $this->connection->fetchAssociative('SELECT id, organization_id, intervention_id, record_status FROM equipment WHERE id = :id OR client_id = :id', ['id' => $id]);

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

    return is_string($value) && '' !== $value ? (str_starts_with($value, '/api/') ? ResourceIriParser::id($value, $collection) : $value) : null;
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
