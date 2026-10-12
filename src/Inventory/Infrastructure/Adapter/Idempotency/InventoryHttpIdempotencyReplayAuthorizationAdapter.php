<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Intervention\Application\Port\Inbound\FreshInterventionScopePort;
use Inventory\Application\Port\Outbound\InventoryHttpResponseVisibilityPort;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function str_ends_with;

/**
 * Class InventoryHttpIdempotencyReplayAuthorizationAdapter.
 *
 * Rechecks uncached owner scope and current grants before restoring an original immutable response.
 *
 * @category Adapter
 */
final readonly class InventoryHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort, InventoryHttpResponseVisibilityPort
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
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $ordinary, private HttpIdempotencyOrganizationAuthorityPort $organizations, private Connection $connection, private FreshInterventionScopePort $interventions, private RequestStack $requests)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (InventoryHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
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
    $consume = in_array($operation, ['inventory_consumptions_create', 'inventory_returns_create'], true);
    $stock = in_array($operation, ['inventory_consumptions_create', 'inventory_consumptions_reconcile', 'inventory_returns_create', 'inventory_corrections_create'], true);
    $this->permission($request, $organization, $consume ? 'organization.inventory.consume' : 'organization.inventory.manage');
    if (in_array($operation, ['inventory_consumptions_create', 'inventory_consumptions_reconcile', 'inventory_returns_create'], true)) {
      $this->permission($request, $organization, 'organization.interventions.execute');
    }
    if ('inventory_corrections_create' === $operation) {
      $this->permission($request, $organization, 'organization.maintenance_cost.manage');
    }
    $body = $this->body($request);
    if (in_array($operation, ['inventory_consumptions_create', 'inventory_corrections_create'], true)) {
      foreach (['partId' => 'inventory_parts', 'warehouseId' => 'inventory_warehouses'] as $field => $table) {
        $id = $this->identifier($body[$field] ?? null);
        if ($this->connection->fetchOne('SELECT organization_id FROM ' . $table . ' WHERE id = :id', ['id' => $id]) !== $organization) {
          $this->notFound();
        }
      }
    }
    $target = null;
    $context = [];
    if ('inventory_consumptions_create' === $operation) {
      $context = ['interventionId' => $this->identifier($body['interventionId'] ?? null), 'workItemId' => $body['workItemId'] ?? null, 'equipmentId' => $body['equipmentId'] ?? null];
    } elseif (in_array($operation, ['inventory_consumptions_reconcile', 'inventory_returns_create'], true)) {
      $original = $this->identifier('inventory_consumptions_reconcile' === $operation ? ($request->uriVariables['id'] ?? null) : ($body['consumptionId'] ?? null));
      $row = $this->connection->fetchAssociative('SELECT organization_id, intervention_id, work_item_id, equipment_id FROM inventory_declarations WHERE id = :id', ['id' => $original]);
      if (false === $row || ($row['organization_id'] ?? null) !== $organization) {
        $this->notFound();
      }
      $context = ['interventionId' => $row['intervention_id'] ?? null, 'workItemId' => $row['work_item_id'] ?? null, 'equipmentId' => $row['equipment_id'] ?? null];
    }
    if ([] !== $context) {
      $work = null === $context['workItemId'] ? null : $this->identifier($context['workItemId']);
      $equipment = null === $context['equipmentId'] ? null : $this->identifier($context['equipmentId']);
      $this->interventions->assertInventoryAccess($organization, $this->identifier($context['interventionId']), $work, $equipment, $request->principalId);
    }
    if (null !== $storedProof || in_array($operation, ['inventory_parts_patch', 'inventory_warehouses_patch'], true)) {
      $target = $this->identifier(null !== $storedProof ? ($storedProof->metadata['resourceId'] ?? null) : ($request->uriVariables['id'] ?? null));
      $kind = $request->operation->resourceKind;
      $table = match ($kind) {
        'inventory_part' => 'inventory_parts',
        'inventory_warehouse' => 'inventory_warehouses',
        'inventory_consumption' => 'inventory_declarations',
        'inventory_movement' => 'inventory_movements',
        default => throw new IdempotencyDeniedException('access_denied'),
      };
      if (null !== $storedProof && ($storedProof->metadata['resourceKind'] ?? null) !== $kind) {
        $this->deny();
      }
      if (str_ends_with($operation, '_patch') && ($request->uriVariables['id'] ?? null) !== $target) {
        $this->deny();
      }
      if ($this->connection->fetchOne('SELECT organization_id FROM ' . $table . ' WHERE id = :id', ['id' => $target]) !== $organization) {
        $this->notFound();
      }
    }
    $financial = $stock && $this->organizations->hasPermission($request->principalId, $organization, 'organization.maintenance_cost.read');
    if ($stock && null !== $storedProof) {
      $originalFinancial = $storedProof->metadata['inventoryFinancialReadable'] ?? null;
      if (!in_array($originalFinancial, ['0', '1'], true) || ('1' === $originalFinancial && !$financial)) {
        $this->deny();
      }
      $financial = '1' === $originalFinancial;
    }
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    $facts['inventoryFinancialReadable'] = $financial ? '1' : '0';
    if (null !== $target) {
      $facts['targetId'] = $target;
    }
    $this->requests->getCurrentRequest()?->attributes->set('_inventory_http_response_visibility', ['organizationId' => $organization, 'financial' => $financial]);

    return $facts;
  }

  /**
   * {@inheritdoc}
   */
  public function financial(string $organizationId): ?bool
  {
    $visibility = $this->requests->getCurrentRequest()?->attributes->get('_inventory_http_response_visibility');

    return is_array($visibility) && ($visibility['organizationId'] ?? null) === $organizationId && is_bool($visibility['financial'] ?? null) ? $visibility['financial'] : null;
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
   * @return never preserves hidden inventory scopes
   */
  private function notFound(): never
  {
    throw new \Inventory\Domain\Exception\InventoryNotFoundException('Inventory resource not found.');
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
