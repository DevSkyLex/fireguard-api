<?php

declare(strict_types=1);

namespace Procurement\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Equipment\Application\Port\Inbound\EquipmentReserveReceiptPort;
use Inventory\Application\Port\Inbound\InventoryPartDirectoryPort;
use Procurement\Domain\Exception\ProcurementException;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use Symfony\Component\Uid\Uuid;

use function array_key_exists;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function strtolower;

use const JSON_THROW_ON_ERROR;

/**
 * Class ProcurementHttpIdempotencyReplayAuthorizationAdapter
 *
 * Rechecks own retained relationships and published inventory/equipment scopes before decoding financial snapshots.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author FireGuard contributors
 */
final readonly class ProcurementHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @since 1.0.0
   *
   * @param OrdinaryHttpReplayAuthorizationPort $ordinary fresh actor and signed requirements
   * @param HttpIdempotencyOrganizationAuthorityPort $organizations fresh owner authority
   * @param Connection $connection explicitly bound main database
   * @param InventoryPartDirectoryPort $parts uncached owner catalog and warehouse scope
   * @param EquipmentReserveReceiptPort $equipment owner catalog read capability, never reservation during replay
   * @param HttpIdempotencyAuthorizationEvidencePort $evidence successful scoped requirements
   *
   * @return void
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $ordinary, private HttpIdempotencyOrganizationAuthorityPort $organizations, private Connection $connection, private InventoryPartDirectoryPort $parts, private EquipmentReserveReceiptPort $equipment, private HttpIdempotencyAuthorizationEvidencePort $evidence)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (ProcurementHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
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

    return $this->ordinary->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $this->assertSupported($request);
    $organization = $this->identifier($request->uriVariables['organizationId'] ?? null);
    if (!$this->organizations->isMemberOf($request->principalId, $organization)) {
      $this->notFound();
    }
    $this->permission($request, $organization, 'organization.procurement.manage');
    $operation = $request->operation->name;
    if ('procurement_individualize' === $operation) {
      $this->permission($request, $organization, 'organization.equipment.write');
    }
    $body = $request->canonicalInput['body'] ?? null;
    $fields = $this->fields(is_array($body) ? ($body['value'] ?? null) : null);
    $row = null;
    $target = $request->uriVariables['id'] ?? null;
    if (null !== $target) {
      $target = $this->identifier($target);
      $kind = match ($operation) {
        'procurement_receive', 'procurement_change_order', 'procurement_place_order', 'procurement_cancel_remaining' => 'purchase_order',
        default => $request->operation->resourceKind,
      };
      $row = $this->resource($organization, $kind, $target);
      $this->relationships($request, $organization, $kind, $row, $fields);
    }
    $this->assertSubmittedOrder($request, $organization, $fields);
    $financial = 'supplier' !== $request->operation->resourceKind && $this->organizations->hasPermission($request->principalId, $organization, 'organization.maintenance_cost.read');
    if (null !== $storedProof) {
      $financial = $this->originalFinancialVisibility($request, $storedProof, $organization, $target, $financial);
    }
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    $facts['procurementFinancialReadable'] = $financial ? '1' : '0';
    if (null !== $target) {
      $facts['procurementTargetId'] = $target;
    }

    return $facts;
  }

  /**
   * Method assertSubmittedOrder
   *
   * Rechecks submitted supplier, catalog goods and cost-writing authority.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the current owner operation
   * @param string $organization the active owner scope
   * @param array<array-key, mixed> $fields canonical submitted fields
   *
   * @return void
   */
  private function assertSubmittedOrder(HttpIdempotencyAuthorizationRequest $request, string $organization, array $fields): void
  {
    if (!in_array($request->operation->name, ['procurement_create_order', 'procurement_change_order'], true)) {
      return;
    }
    if (array_key_exists('supplierId', $fields)) {
      $this->resource($organization, 'supplier', $this->identifier($fields['supplierId']));
    }
    $lines = $fields['lines'] ?? [];
    if (is_array($lines)) {
      foreach ($lines as $line) {
        $line = $this->fields($line);
        $this->catalogLine($organization, $line);
        if (array_key_exists('unitCost', $line)) {
          $this->permission($request, $organization, 'organization.maintenance_cost.manage');
        }
      }
    }
  }

  /**
   * Method originalFinancialVisibility
   *
   * Rechecks the original result lineage and financial audience before disclosure.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the current owner operation
   * @param IdempotencyProof $storedProof authenticated original result identity
   * @param string $organization the active owner scope
   * @param string|null $target the current route target
   * @param bool $financial the current financial read grant
   *
   * @return bool the retained financial projection
   */
  private function originalFinancialVisibility(HttpIdempotencyAuthorizationRequest $request, IdempotencyProof $storedProof, string $organization, ?string $target, bool $financial): bool
  {
    if (($storedProof->metadata['resourceKind'] ?? null) !== $request->operation->resourceKind || (null !== $target && ($storedProof->metadata['procurementTargetId'] ?? null) !== $target)) {
      $this->deny();
    }
    $originalFinancial = $storedProof->metadata['procurementFinancialReadable'] ?? null;
    if (!in_array($originalFinancial, ['0', '1'], true) || ('1' === $originalFinancial && !$financial)) {
      $this->deny();
    }
    $resultId = $this->identifier($storedProof->metadata['resourceId'] ?? null);
    $result = $this->resource($organization, $request->operation->resourceKind, $resultId);
    if ('procurement_receive' === $request->operation->name && ($result['order_id'] ?? null) !== $target) {
      $this->notFound();
    }
    if (null !== $target && 'procurement_receive' !== $request->operation->name && $resultId !== $target) {
      $this->deny();
    }
    $this->relationships($request, $organization, $request->operation->resourceKind, $result, []);

    return '1' === $originalFinancial;
  }

  /**
   * Method relationships
   *
   * @since 1.0.0
   *
   * @param HttpIdempotencyAuthorizationRequest $request scoped command
   * @param string $organization original organization
   * @param string|null $kind owner-declared result or route kind
   * @param array<string, mixed> $row current own row
   * @param array<array-key, mixed> $fields canonical submitted fields
   *
   * @return void
   */
  private function relationships(HttpIdempotencyAuthorizationRequest $request, string $organization, ?string $kind, array $row, array $fields): void
  {
    if ('purchase_order' === $kind) {
      $this->orderRelationships($request, $organization, $row, $fields);
    } elseif ('receipt' === $kind) {
      $order = $this->resource($organization, 'purchase_order', $this->identifier($row['order_id'] ?? null));
      $this->relationships($request, $organization, 'purchase_order', $order, ['lineId' => $row['line_id'] ?? null]);
      if ('part' === ($row['kind'] ?? null) && in_array($request->operation->name, ['procurement_receive', 'procurement_return', 'procurement_reconcile_return'], true)) {
        $this->permission($request, $organization, 'organization.inventory.manage');
      }
    } elseif ('return' === $kind) {
      $receipt = $this->resource($organization, 'receipt', $this->identifier($row['receipt_id'] ?? null));
      $this->relationships($request, $organization, 'receipt', $receipt, []);
    }
    $warehouse = $fields['warehouseId'] ?? ($row['warehouse_id'] ?? null);
    if (null !== $warehouse && !$this->parts->warehouseExistsActive($organization, $this->identifier($warehouse))) {
      $this->notFound();
    }
  }

  /**
   * Method orderRelationships
   *
   * Rechecks the supplier, each retained good and the requested receipt line.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the current owner operation
   * @param string $organization the active owner scope
   * @param array<string, mixed> $row the fresh purchase order
   * @param array<array-key, mixed> $fields canonical requested line fields
   *
   * @return void
   */
  private function orderRelationships(HttpIdempotencyAuthorizationRequest $request, string $organization, array $row, array $fields): void
  {
    $this->resource($organization, 'supplier', $this->identifier($row['supplier_id'] ?? null));
    $lines = $this->orderLines($row);
    $requestedLine = $fields['lineId'] ?? null;
    $found = null === $requestedLine;
    foreach ($lines as $line) {
      if (!is_array($line)) {
        $this->notFound();
      }
      $this->catalogLine($organization, $line);
      if ($requestedLine === ($line['id'] ?? null)) {
        $found = true;
        if ('procurement_receive' === $request->operation->name && 'part' === ($line['kind'] ?? null)) {
          $this->permission($request, $organization, 'organization.inventory.manage');
        }
      }
    }
    if (!$found) {
      $this->notFound();
    }
  }

  /**
   * Method orderLines
   *
   * Restores retained JSON lines without accepting malformed row representations.
   *
   * @access private
   *
   * @param array<string, mixed> $row the fresh purchase order
   *
   * @return array<array-key, mixed> the retained line list
   */
  private function orderLines(array $row): array
  {
    $lines = $row['lines'] ?? [];
    $lines = is_string($lines) ? json_decode($lines, true, 32, JSON_THROW_ON_ERROR) : $lines;
    if (!is_array($lines)) {
      $this->notFound();
    }

    return $lines;
  }

  /**
   * Method catalogLine
   *
   * @since 1.0.0
   *
   * @param string $organization current scope
   * @param array<array-key, mixed> $line canonical or retained goods
   *
   * @return void
   */
  private function catalogLine(string $organization, array $line): void
  {
    if ('part' === ($line['kind'] ?? null) && !$this->parts->existsActive($organization, $this->identifier($line['partId'] ?? null))) {
      $this->notFound();
    }
    if ('equipment_to_individualize' === ($line['kind'] ?? null) && (!is_string($line['typeCode'] ?? null) || !$this->equipment->supportsType($organization, $line['typeCode']))) {
      $this->notFound();
    }
  }

  /**
   * Method resource
   *
   * @since 1.0.0
   *
   * @param string $organization current scope
   * @param string|null $kind owner kind
   * @param string $id resource identity
   *
   * @return array<string, mixed> uncached row
   */
  private function resource(string $organization, ?string $kind, string $id): array
  {
    $table = match ($kind) {
      'supplier' => 'procurement_suppliers',
      'purchase_order' => 'procurement_orders',
      'receipt' => 'procurement_receipts',
      'return' => 'procurement_returns',
      default => throw new IdempotencyDeniedException('access_denied'),
    };
    $row = $this->connection->fetchAssociative('SELECT * FROM ' . $table . ' WHERE id = :id', ['id' => $id]);
    if (false === $row || ($row['organization_id'] ?? null) !== $organization) {
      $this->notFound();
    }

    return $row;
  }

  /**
   * Method fields
   *
   * @since 1.0.0
   *
   * @param mixed $object tagged canonical JSON object
   *
   * @return array<array-key, mixed> fields
   */
  private function fields(mixed $object): array
  {
    return is_array($object) && 'object' === ($object['kind'] ?? null) && is_array($object['fields'] ?? null) ? $object['fields'] : [];
  }

  /**
   * Method permission
   *
   * @since 1.0.0
   *
   * @param HttpIdempotencyAuthorizationRequest $request current actor
   * @param string $organization scope
   * @param string $permission owner requirement
   *
   * @return void
   */
  private function permission(HttpIdempotencyAuthorizationRequest $request, string $organization, string $permission): void
  {
    if (!$this->organizations->hasPermission($request->principalId, $organization, $permission)) {
      $this->deny();
    }
    $this->evidence->record($request->principalId, $organization, [$permission]);
  }

  /**
   * Method assertSupported
   *
   * @since 1.0.0
   *
   * @param HttpIdempotencyAuthorizationRequest $request declared owner operation
   *
   * @return void
   */
  private function assertSupported(HttpIdempotencyAuthorizationRequest $request): void
  {
    if (!$this->supports($request->operation->name)) {
      $this->deny();
    }
  }

  /**
   * Method identifier
   *
   * @since 1.0.0
   *
   * @param mixed $value trusted or signed identity
   *
   * @return string valid UUID
   */
  private function identifier(mixed $value): string
  {
    if (!is_string($value) || !Uuid::isValid($value)) {
      $this->notFound();
    }

    return strtolower($value);
  }

  /**
   * Method notFound
   *
   * @since 1.0.0
   *
   * @return never hidden original or related scope
   */
  private function notFound(): never
  {
    throw ProcurementException::notFound();
  }

  /**
   * Method deny
   *
   * @since 1.0.0
   *
   * @return never current grant or signed disclosure authority lost
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
  // #endregion
}
