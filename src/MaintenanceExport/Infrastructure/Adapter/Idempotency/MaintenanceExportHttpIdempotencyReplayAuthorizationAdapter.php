<?php

declare(strict_types=1);

namespace MaintenanceExport\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use MaintenanceExport\Application\Port\Outbound\MaintenanceExportIdentityPort;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use Symfony\Component\Uid\Uuid;

use function in_array;
use function is_array;
use function is_string;

/**
 * Class MaintenanceExportHttpIdempotencyReplayAuthorizationAdapter.
 *
 * Rechecks uncached owner scope and current grants before restoring an original immutable response.
 *
 * @category Adapter
 */
final readonly class MaintenanceExportHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param OrdinaryHttpReplayAuthorizationPort $ordinary current caller and signed original requirements
   * @param HttpIdempotencyOrganizationAuthorityPort $organizations uncached effective organization authority
   * @param Connection $connection explicitly wired owning main database
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $ordinary, private HttpIdempotencyOrganizationAuthorityPort $organizations, private Connection $connection, private MaintenanceExportIdentityPort $identities)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (MaintenanceExportHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
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
    if (!$this->supports($request->operation->name)) {
      $this->deny();
    }

    return $this->ordinary->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $organization = $this->organization($request);
    $operation = $request->operation->name;
    $this->permission($request, $organization, 'organization.maintenance_exports.read');
    $this->permission($request, $organization, 'confirm_maintenance_export' === $operation ? 'organization.maintenance_exports.confirm' : 'organization.maintenance_exports.manage');
    $fields = $this->body($request);
    $target = null;
    $financial = false;
    if ('write_maintenance_export_reference' === $operation) {
      $type = $request->uriVariables['resourceType'] ?? null;
      $resource = $this->identifier($request->uriVariables['resourceId'] ?? null);
      if (!is_string($type) || !in_array($type, ['customer', 'site', 'equipment'], true) || !$this->identities->exists($organization, $type, $resource)) {
        $this->notFound();
      }
      if (null !== $storedProof) {
        $target = $this->identifier($storedProof->metadata['resourceId'] ?? null);
        if (($storedProof->metadata['resourceKind'] ?? null) !== 'maintenance_reference') {
          $this->deny();
        }
        $row = $this->connection->fetchAssociative('SELECT organization_id, resource_type, resource_id FROM maintenance_external_references WHERE id = :id', ['id' => $target]);
        if (false === $row || ($row['organization_id'] ?? null) !== $organization || ($row['resource_type'] ?? null) !== $type || ($row['resource_id'] ?? null) !== $resource) {
          $this->notFound();
        }
      }
    } else {
      if ('create_maintenance_export' === $operation) {
        $financial = true === ($fields['includeInternalCosts'] ?? false);
      } else {
        $parent = $this->identifier($request->uriVariables['id'] ?? null);
        $parentRow = $this->document($parent, $organization);
        $financial = $this->financialFlag($parentRow['include_internal_costs'] ?? null);
        if (null !== $storedProof && ($storedProof->metadata['maintenanceExportParentId'] ?? null) !== $parent) {
          $this->deny();
        }
      }
      if (null !== $storedProof) {
        $target = $this->identifier($storedProof->metadata['resourceId'] ?? null);
        if (($storedProof->metadata['resourceKind'] ?? null) !== 'maintenance_export') {
          $this->deny();
        }
        $saved = $this->document($target, $organization);
        $financial = $this->financialFlag($saved['include_internal_costs'] ?? null);
        if (($storedProof->metadata['maintenanceExportFinancial'] ?? null) !== ($financial ? '1' : '0')) {
          $this->deny();
        }
      }
    }
    if ($financial) {
      $this->permission($request, $organization, 'organization.maintenance_cost.read');
    }
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    $facts['maintenanceExportFinancial'] = $financial ? '1' : '0';
    if (isset($parent)) {
      $facts['maintenanceExportParentId'] = $parent;
    }
    if (null !== $target) {
      $facts['targetId'] = $target;
    }

    return $facts;
  }

  /**
   * Method document.
   *
   * @param string $id retained artifact identifier
   * @param string $organization exact owner scope
   *
   * @return array<string, mixed> fresh scalar artifact authorization facts
   */
  private function document(string $id, string $organization): array
  {
    $row = $this->connection->fetchAssociative('SELECT organization_id, include_internal_costs FROM maintenance_export_documents WHERE id = :id', ['id' => $id]);
    if (false === $row || ($row['organization_id'] ?? null) !== $organization) {
      $this->notFound();
    }

    return $row;
  }

  /**
   * Method financialFlag.
   *
   * @param mixed $value native PostgreSQL boolean
   *
   * @return bool explicitly restored private-export flag
   */
  private function financialFlag(mixed $value): bool
  {
    if (in_array($value, [true, 't', 1, '1'], true)) {
      return true;
    }
    if (in_array($value, [false, 'f', 0, '0'], true)) {
      return false;
    }

    $this->deny();
  }

  /**
   * Method body.
   *
   * @param HttpIdempotencyAuthorizationRequest $request canonical wire input
   *
   * @return array<array-key, mixed> explicitly tagged JSON object fields
   */
  private function body(HttpIdempotencyAuthorizationRequest $request): array
  {
    $body = $request->canonicalInput['body'] ?? null;
    $value = is_array($body) ? ($body['value'] ?? null) : null;
    if (!is_array($value) || 'object' !== ($value['kind'] ?? null) || !is_array($value['fields'] ?? null)) {
      return [];
    }

    return $value['fields'];
  }

  /**
   * Method organization.
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated route scope
   *
   * @return string exact active organization scope
   */
  private function organization(HttpIdempotencyAuthorizationRequest $request): string
  {
    if (!$this->supports($request->operation->name)) {
      $this->deny();
    }
    $organization = $this->identifier($request->uriVariables['organizationId'] ?? null);
    if (!$this->organizations->isMemberOf($request->principalId, $organization)) {
      $this->notFound();
    }

    return $organization;
  }

  /**
   * Method identifier.
   *
   * @param mixed $value owner resource identity
   *
   * @return string validated UUID
   */
  private function identifier(mixed $value): string
  {
    if (!is_string($value) || !Uuid::isValid($value)) {
      $this->notFound();
    }

    return $value;
  }

  /**
   * Method permission.
   *
   * @param HttpIdempotencyAuthorizationRequest $request verified actor
   * @param string $organization exact owner scope
   * @param string $permission explicit registered owner grant
   *
   * @return void
   */
  private function permission(HttpIdempotencyAuthorizationRequest $request, string $organization, string $permission): void
  {
    if (!$this->organizations->hasPermission($request->principalId, $organization, $permission)) {
      $this->deny();
    }
  }

  /**
   * Method notFound.
   *
   * @return never preserves hidden export scopes
   */
  private function notFound(): never
  {
    throw \MaintenanceExport\Domain\Exception\MaintenanceExportException::notFound();
  }

  /**
   * Method deny.
   *
   * @return never denies before any receipt result constructor is invoked
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
  // #endregion
}
