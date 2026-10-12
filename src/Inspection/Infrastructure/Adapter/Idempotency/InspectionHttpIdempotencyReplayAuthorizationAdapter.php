<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Inspection\Application\Port\Outbound\InspectionReportEntitlementPort;
use Inspection\Domain\Exception\InspectionNotFoundException;
use Intervention\Application\Port\Inbound\FreshInterventionScopePort;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Contract\Http\{HttpIdempotencyAuthorizationRequest, ResourceIriParser};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

use function in_array;
use function is_array;
use function is_string;
use function str_starts_with;

/** Rechecks current Inspection-owned scope and original grants before any saved application result is opened. @category Adapter */
final readonly class InspectionHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  public function __construct(private Connection $connection, private OrdinaryHttpReplayAuthorizationPort $ordinary, private FreshOrganizationAuthorizationPort $authorization, private FreshInterventionScopePort $interventions, private ?HttpIdempotencyAuthorizationEvidencePort $evidence = null, private ?InspectionReportEntitlementPort $entitlement = null)
  {
  }

  public function supports(string $operation): bool
  {
    foreach (InspectionHttpIdempotencyOperationContributionAdapter::definitions() as $definition) {
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
    if (in_array($request->operation->name, ['inspection_export', 'inspection_export_non_conformities', 'inspection_export_report', 'inspection_export_non_conformities_report'], true)) {
      return $this->authorizeExport($request, $storedProof);
    }
    $row = $this->target($request, $storedProof);
    $organization = $this->value($row, 'organization_id') ?? $this->route($request, 'organizationId') ?? $this->bodyId($request, 'organization', 'organizations');
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    $organization ??= $facts['organizationId'] ?? null;
    if (null === $organization) {
      $this->deny();
    }
    if ('canonical_inspection_patch' === $request->operation->name) {
      $this->requirePermissions($request, $organization, ['organization.inspection.read']);
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
    $permission = 'organization.inspection.write';
    $intervention = $this->bodyId($request, 'intervention', 'interventions');
    if (null === $intervention && ('draft' === $this->value($row, 'record_status') || 'inspection_response' === $request->operation->resourceKind || null === $row)) {
      $intervention = $this->value($row, 'intervention_id');
    }
    if (null !== $intervention && in_array($request->operation->name, ['_api_/inspections_post', 'canonical_inspection_put', 'inspection_create', 'canonical_inspection_patch', 'canonical_inspection_delete', '_api_/inspection-responses_post', 'inspection_response_put', '_api_/inspection-responses/{id}_patch', '_api_/inspection-responses/{id}_delete'], true)) {
      $permission = $this->interventions->mutationPermission($intervention, $request->principalId, $organization);
      $facts['interventionId'] = $intervention;
      $facts['interventionPermission'] = $permission;
    }
    $this->requirePermissions($request, $organization, [$permission]);

    return $facts;
  }

  /**
   * @return array<string, string> current authorized export scope
   */
  private function authorizeExport(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof): array
  {
    $organization = $this->route($request, 'organizationId');
    if (null === $organization) {
      throw new InspectionNotFoundException('Organization not found.');
    }
    if ('inspection_export_report' === $request->operation->name) {
      $id = $this->route($request, 'inspectionId');
      if (null === $id || (null !== $proof && ($proof->metadata['resourceId'] ?? null) !== $id)) {
        throw new InspectionNotFoundException('Inspection not found.');
      }
      $currentOrganization = $this->connection->fetchOne('SELECT organization_id FROM inspections WHERE id = :id', ['id' => $id]);
      if ($currentOrganization !== $organization) {
        throw new InspectionNotFoundException('Inspection not found.');
      }
    }
    if (!$this->authorization->isMemberOf($request->principalId, $organization)) {
      throw new InspectionNotFoundException('Organization not found.');
    }
    $facts = $this->ordinary->authorize($request, $proof, $organization);
    $this->requirePermissions($request, $organization, ['organization.inspection.read']);
    if (in_array($request->operation->name, ['inspection_export_report', 'inspection_export_non_conformities_report'], true) && true !== $this->entitlement?->isExportEntitled($organization)) {
      $this->deny();
    }

    return $facts;
  }

  /**
   * @return array<string, mixed>|null
   */
  private function target(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof): ?array
  {
    $kind = $request->operation->resourceKind;
    $saved = $proof?->metadata['resourceId'] ?? null;
    if ('checklist' === $kind) {
      $id = $saved ?? $this->route($request, 'checklistId');
      if (null === $id) {
        return null;
      }
      $row = $this->connection->fetchAssociative('SELECT organization_id FROM checklists WHERE id = :id', ['id' => $id]);

      return false === $row ? null : $row;
    }
    if ('inspection_response' === $kind) {
      $id = $saved ?? $this->route($request, 'id');
      if (null !== $id) {
        $row = $this->connection->fetchAssociative('SELECT organization_id, intervention_id, record_status FROM inspection_responses WHERE id = :id OR client_id = :id', ['id' => $id]);
        if (false !== $row) {
          return $row;
        }
      }
      $id = $this->bodyId($request, 'inspection', 'inspections');
    } elseif ('non_conformity' === $kind || 'inspection_attachment' === $kind) {
      $attachment = 'inspection_attachment' === $kind ? ($saved ?? $this->route($request, 'id')) : null;
      if (null !== $attachment) {
        $row = $this->connection->fetchAssociative('SELECT i.id, i.organization_id, i.intervention_id, i.record_status FROM inspection_attachments a JOIN inspections i ON i.id = a.inspection_id WHERE a.id = :id', ['id' => $attachment]);
        if (false !== $row) {
          $this->assertParent($request, $row);

          return $row;
        }
      }
      $finding = $this->route($request, 'nonConformityId') ?? ('non_conformity' === $kind ? $saved : null);
      if (null !== $finding) {
        $row = $this->connection->fetchAssociative('SELECT i.id, i.organization_id, i.intervention_id, i.record_status FROM non_conformities n JOIN inspections i ON i.id = n.inspection_id WHERE n.id = :id', ['id' => $finding]);
        if (false !== $row) {
          $this->assertParent($request, $row);

          return $row;
        }
      }
      $id = $this->route($request, 'inspectionId');
    } else {
      $id = $saved ?? $this->route($request, 'inspectionId') ?? $this->route($request, 'id');
    }
    if (null === $id) {
      return null;
    }
    $row = $this->connection->fetchAssociative('SELECT id, organization_id, intervention_id, record_status FROM inspections WHERE id = :id OR client_id = :id', ['id' => $id]);

    return false === $row ? null : $row;
  }

  /**
   * @param array<string, mixed> $row
   */
  private function assertParent(HttpIdempotencyAuthorizationRequest $request, array $row): void
  {
    $parent = $this->route($request, 'inspectionId');
    if (null !== $parent && $this->value($row, 'id') !== $parent) {
      $this->deny();
    }
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
