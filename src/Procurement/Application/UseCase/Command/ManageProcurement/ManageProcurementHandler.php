<?php

declare(strict_types=1);

namespace Procurement\Application\UseCase\Command\ManageProcurement;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Equipment\Application\Contract\Procurement\EquipmentReserveReceiptRequest;
use Equipment\Application\Port\Inbound\EquipmentReserveReceiptPort;
use Inventory\Application\Contract\Stock\{InventoryReceiptRequest, InventoryReceiptReturnRequest};
use Inventory\Application\Port\Inbound\{InventoryPartDirectoryPort, InventoryStockReceiptPort};
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Procurement\Application\Contract\{ProcurementOperationState, ProcurementReceiptState, ProcurementReturnState};
use Procurement\Application\Port\Outbound\Persistence\ProcurementRepositoryPort;
use Procurement\Application\Service\ProcurementProjection;
use Procurement\Domain\Event\ProcurementChangedEvent;
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Domain\Model\{PurchaseOrder, Supplier};
use Procurement\Domain\ValueObject\ProcurementLine;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, UuidGeneratorPort};
use Shared\Domain\ValueObject\{DecimalAmount, Uuid};

use function array_is_list;
use function array_key_exists;
use function array_unique;
use function array_values;
use function count;
use function hash;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function ksort;
use function preg_match;
use function strlen;
use function strtolower;
use function trim;

use const JSON_THROW_ON_ERROR;

/** Reserves remaining quantities and stock receipts atomically in the main database. */
final readonly class ManageProcurementHandler implements CommandHandler
{
  public function __construct(
    private ProcurementRepositoryPort $repository,
    private OrganizationAuthorizationPort $authorization,
    private MaintenanceCurrencyPort $currencies,
    private InventoryPartDirectoryPort $parts,
    private InventoryStockReceiptPort $stock,
    private EquipmentReserveReceiptPort $equipment,
    private ProcurementProjection $projection,
    private ClockPort $clock,
    private UuidGeneratorPort $ids,
    private EventDispatcherPort $events,
  ) {
  }

  public function __invoke(ManageProcurementCommand $command): ManageProcurementResult
  {
    $command = new ManageProcurementCommand(strtolower($command->actorId), strtolower($command->organizationId), $command->action, null === $command->id ? null : strtolower($command->id), $command->expectedRevision, $command->payload);
    $this->access($command, 'organization.procurement.manage');
    if ('individualize' === $command->action) {
      $this->access($command, 'organization.equipment.write');
    }
    $finance = $this->authorization->hasPermission($command->actorId, $command->organizationId, 'organization.maintenance_cost.read');

    return $this->repository->synchronized($command->organizationId, function () use ($command, $finance): ManageProcurementResult {
      $creation = in_array($command->action, ['create_supplier', 'create_order'], true);
      $operationId = $creation && null !== ($command->payload['clientOperationId'] ?? null) ? $this->operationId($command) : null;
      $creationPayload = $command->payload;
      unset($creationPayload['clientOperationId']);
      $fingerprint = null === $operationId ? '' : $this->fingerprint($this->creationPayload($creationPayload));
      if (null !== $operationId) {
        $replay = $this->replay($command, $operationId, $command->action, $fingerprint, $finance);
        if (null !== $replay) {
          return $replay;
        }
      }
      $result = match ($command->action) {
        'create_supplier', 'change_supplier', 'archive_supplier' => $this->supplier($command),
        'create_order', 'change_order', 'order', 'cancel_remaining' => $this->order($command, $finance),
        'receive' => $this->receive($command, $finance),
        'individualize' => $this->individualize($command, $finance),
        'return' => $this->returnReceipt($command, $finance),
        'reconcile_return' => $this->reconcileReturn($command, $finance),
        default => throw ProcurementException::invalid('Unknown procurement mutation.'),
      };
      if (null !== $operationId) {
        $resourceId = $result->data['id'] ?? null;
        if (!is_string($resourceId)) {
          throw ProcurementException::conflict('The created resource has no durable identity.');
        }
        $this->repository->saveOperation(new ProcurementOperationState($command->organizationId, $operationId, $command->action, $fingerprint, $resourceId, $command->payload + ['actorId' => $command->actorId]));
      }
      if (!$result->replayed) {
        $resourceId = $result->data['id'] ?? null;
        if (is_string($resourceId)) {
          $this->events->dispatch(new ProcurementChangedEvent($command->organizationId, $resourceId, $command->action, $this->clock->now()));
        }
      }

      return $result;
    });
  }

  private function supplier(ManageProcurementCommand $command): ManageProcurementResult
  {
    $this->fields($command->payload, 'create_supplier' === $command->action ? ['name', 'code', 'email', 'phone', 'contacts', 'clientOperationId'] : ['name', 'code', 'email', 'phone', 'contacts']);
    $now = $this->clock->now();
    if ('create_supplier' === $command->action) {
      $supplier = Supplier::create($this->ids->generate(), $command->organizationId, $this->text($command->payload, 'name'), $this->optionalText($command->payload, 'code'), $this->optionalText($command->payload, 'email'), $this->optionalText($command->payload, 'phone'), $this->contacts($command->payload['contacts'] ?? []), $now);
    } else {
      $supplier = $this->repository->supplier($command->organizationId, $command->id ?? '') ?? throw ProcurementException::notFound();
      $revision = $this->revision($command);
      if ('archive_supplier' === $command->action) {
        $supplier->archive($revision, $now);
      } else {
        $supplier->change($revision, $this->textOr($command->payload, 'name', $supplier->name()), $this->optionalOr($command->payload, 'code', $supplier->code()), $this->optionalOr($command->payload, 'email', $supplier->email()), $this->optionalOr($command->payload, 'phone', $supplier->phone()), $this->contacts($command->payload['contacts'] ?? $supplier->contacts()), $now);
      }
    }
    $this->repository->saveSupplier($supplier);

    return new ManageProcurementResult('supplier', $this->projection->supplier($supplier));
  }

  private function order(ManageProcurementCommand $command, bool $finance): ManageProcurementResult
  {
    $this->fields($command->payload, 'create_order' === $command->action ? ['name', 'supplierId', 'lines', 'clientOperationId'] : ['name', 'supplierId', 'lines']);
    $now = $this->clock->now();
    if ('create_order' === $command->action) {
      $supplierId = $this->uuid($this->text($command->payload, 'supplierId'));
      $this->activeSupplier($command->organizationId, $supplierId);
      $order = PurchaseOrder::create($this->ids->generate(), $command->organizationId, $supplierId, $this->currencies->lock($command->organizationId), $this->text($command->payload, 'name'), $this->lines($command, $command->payload['lines'] ?? [], []), $now);
    } else {
      $order = $this->repository->order($command->organizationId, $command->id ?? '') ?? throw ProcurementException::notFound();
      $revision = $this->revision($command);
      if ('order' === $command->action) {
        $this->activeSupplier($command->organizationId, $order->supplierId());
        $order->order($revision, $now);
      } elseif ('cancel_remaining' === $command->action) {
        $order->cancelRemaining($revision, $now);
      } else {
        $supplierId = $this->uuid($this->textOr($command->payload, 'supplierId', $order->supplierId()));
        $this->activeSupplier($command->organizationId, $supplierId);
        $lines = array_key_exists('lines', $command->payload) ? $this->lines($command, $command->payload['lines'], $order->lines()) : $order->lines();
        $order->changeDraft($revision, $supplierId, $order->currency(), $this->textOr($command->payload, 'name', $order->name()), $lines, $now);
      }
    }
    $this->repository->saveOrder($order);

    return new ManageProcurementResult('order', $this->projection->order($order, $finance));
  }

  private function receive(ManageProcurementCommand $command, bool $finance): ManageProcurementResult
  {
    $this->fields($command->payload, ['lineId', 'warehouseId', 'quantity', 'receivedAt', 'clientOperationId']);
    $order = $this->repository->order($command->organizationId, $command->id ?? '') ?? throw ProcurementException::notFound();
    $lineId = $this->uuid($this->text($command->payload, 'lineId'));
    $line = $this->lineById($order, $lineId);
    if ('part' === $line->kind) {
      $this->access($command, 'organization.inventory.manage');
    }
    $quantity = $this->quantity($command->payload['quantity'] ?? null);
    $warehouseId = $this->optionalText($command->payload, 'warehouseId');
    $warehouseId = null === $warehouseId ? null : $this->uuid($warehouseId);
    $receivedAt = $this->date($this->text($command->payload, 'receivedAt'));
    if ($receivedAt > $this->clock->now()) {
      throw ProcurementException::invalid('A physical receipt cannot occur in the future.');
    }
    $operationId = $this->operationId($command);
    $fingerprint = $this->fingerprint(['orderId' => $order->id, 'lineId' => $lineId, 'quantity' => $quantity, 'warehouseId' => $warehouseId, 'receivedAt' => $receivedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP')]);
    $replay = $this->replay($command, $operationId, 'receive', $fingerprint, $finance);
    if (null !== $replay) {
      return $replay;
    }
    if ('equipment_to_individualize' === $line->kind && (null !== $warehouseId || !DecimalAmount::fromString($quantity)->isInteger() || DecimalAmount::fromString($quantity)->compareTo(DecimalAmount::fromInt(100)) > 0)) {
      throw ProcurementException::invalid('Equipment receipts contain at most 100 units and no quantitative warehouse stock.');
    }
    if ('part' === $line->kind && (null === $warehouseId || !$this->parts->warehouseExistsActive($command->organizationId, $this->uuid($warehouseId)))) {
      throw ProcurementException::notFound();
    }
    $order->recordReceipt($this->revision($command), $lineId, $quantity, $this->clock->now());
    $receipt = new ProcurementReceiptState($this->ids->generate(), $command->organizationId, $order->id, $lineId, $line->kind, $quantity, $warehouseId, $line->unitCost, $order->currency(), $receivedAt, $command->actorId, $this->clock->now());
    if ('part' === $line->kind) {
      $currency = $this->currencies->lock($command->organizationId);
      if ($currency !== $order->currency()) {
        throw ProcurementException::conflict('The organization currency differs from the order currency.');
      }
      $stock = $this->stock->receive(new InventoryReceiptRequest(organizationId: $command->organizationId, partId: $line->partId ?? throw ProcurementException::invalid('Missing part.'), warehouseId: $warehouseId ?? throw ProcurementException::invalid('Missing warehouse.'), quantity: $quantity, unitCost: $line->unitCost, currency: $currency, clientOperationId: $operationId, sourceReceiptId: $receipt->id, actorId: $command->actorId));
      $receipt->inventoryMovementId = $stock->movementId;
    }
    $this->repository->saveOrder($order);
    $this->repository->saveReceipt($receipt);
    $this->repository->saveOperation(new ProcurementOperationState($command->organizationId, $operationId, 'receive', $fingerprint, $receipt->id, $command->payload + ['actorId' => $command->actorId]));

    return new ManageProcurementResult('receipt', $this->projection->receipt($receipt, $finance));
  }

  private function individualize(ManageProcurementCommand $command, bool $finance): ManageProcurementResult
  {
    $this->fields($command->payload, ['clientOperationId']);
    $receipt = $this->repository->receipt($command->organizationId, $command->id ?? '') ?? throw ProcurementException::notFound();
    $operationId = $this->operationId($command);
    $fingerprint = $this->fingerprint(['receiptId' => $receipt->id]);
    $replay = $this->replay($command, $operationId, 'individualize', $fingerprint, $finance);
    if (null !== $replay) {
      return $replay;
    }
    $this->receiptRevision($command, $receipt);
    if ('equipment_to_individualize' !== $receipt->kind) {
      throw ProcurementException::conflict('Only an equipment receipt can be individualized.');
    }
    $remaining = DecimalAmount::fromString($receipt->quantity)->subtract(DecimalAmount::fromString($receipt->returnedQuantity));
    if ([] === $receipt->equipmentIds && !$remaining->isZero()) {
      $order = $this->repository->order($command->organizationId, $receipt->orderId) ?? throw ProcurementException::notFound();
      $line = $this->lineById($order, $receipt->lineId);
      $result = $this->equipment->reserve(new EquipmentReserveReceiptRequest($command->organizationId, $command->actorId, $line->typeCode ?? throw ProcurementException::invalid('Missing equipment type.'), $this->equipmentTemplate($line->identityTemplate), $remaining->toInt()));
      $receipt->blockedReason = $result->blockedReason;
      if (null === $result->blockedReason) {
        if (count($result->equipmentIds) !== $remaining->toInt()) {
          throw ProcurementException::conflict('The equipment bridge returned an incomplete batch.');
        }
        $receipt->equipmentIds = $result->equipmentIds;
      }
      ++$receipt->revision;
      $this->repository->saveReceipt($receipt);
    }
    $this->repository->saveOperation(new ProcurementOperationState($command->organizationId, $operationId, 'individualize', $fingerprint, $receipt->id, $command->payload + ['actorId' => $command->actorId, 'blockedReason' => $receipt->blockedReason]));

    return new ManageProcurementResult('receipt', $this->projection->receipt($receipt, $finance));
  }

  private function returnReceipt(ManageProcurementCommand $command, bool $finance): ManageProcurementResult
  {
    $this->fields($command->payload, ['quantity', 'reason', 'clientOperationId']);
    $receipt = $this->repository->receipt($command->organizationId, $command->id ?? '') ?? throw ProcurementException::notFound();
    $quantity = $this->quantity($command->payload['quantity'] ?? null);
    if ('part' === $receipt->kind) {
      $this->access($command, 'organization.inventory.manage');
    }
    $reason = trim($this->text($command->payload, 'reason'));
    if ('' === $reason || strlen($reason) > 2000) {
      throw ProcurementException::invalid('A motivated return reason is required.');
    }
    $operationId = $this->operationId($command);
    $fingerprint = $this->fingerprint(['receiptId' => $receipt->id, 'quantity' => $quantity, 'reason' => $reason]);
    $replay = $this->replay($command, $operationId, 'return', $fingerprint, $finance);
    if (null !== $replay) {
      return $replay;
    }
    $this->receiptRevision($command, $receipt);
    if (DecimalAmount::fromString($quantity)->compareTo(DecimalAmount::fromString($receipt->quantity)->subtract(DecimalAmount::fromString($receipt->returnedQuantity))) > 0) {
      throw ProcurementException::conflict('The return exceeds the remaining received quantity.');
    }
    if ([] !== $receipt->equipmentIds) {
      throw ProcurementException::conflict('Individualized equipment must be handled in its retained park lifecycle.');
    }
    $order = $this->repository->order($command->organizationId, $receipt->orderId) ?? throw ProcurementException::notFound();
    $return = new ProcurementReturnState($this->ids->generate(), $command->organizationId, $receipt->id, $operationId, $quantity, $reason, $command->actorId, $this->clock->now());
    $this->repository->saveReturn($return);
    if ('part' === $receipt->kind) {
      $this->currencies->lock($command->organizationId);
      $movement = $this->stock->tryReverse(new InventoryReceiptReturnRequest(organizationId: $command->organizationId, movementId: $receipt->inventoryMovementId ?? throw ProcurementException::conflict('No stock receipt to reverse.'), quantity: $quantity, reason: $reason, clientOperationId: $operationId, actorId: $command->actorId));
      $return->blockedReason = $movement->blockedReason;
      if (null === $movement->blockedReason) {
        $return->inventoryMovementId = $movement->movementId;
      }
    }
    if (null === $return->blockedReason) {
      $return->status = 'confirmed';
      $return->reconciledAt = $this->clock->now();
    } else {
      $receipt->pendingReturnQuantity = DecimalAmount::fromString($receipt->pendingReturnQuantity)->add(DecimalAmount::fromString($quantity))->toString();
    }
    $order->recordReturn($order->revision(), $receipt->lineId, $quantity, $this->clock->now());
    $receipt->returnedQuantity = DecimalAmount::fromString($receipt->returnedQuantity)->add(DecimalAmount::fromString($quantity))->toString();
    if ($receipt->returnedQuantity === $receipt->quantity) {
      $receipt->blockedReason = null;
    }
    ++$receipt->revision;
    $this->repository->saveOrder($order);
    $this->repository->saveReturn($return);
    $this->repository->saveReceipt($receipt);
    $this->repository->saveOperation(new ProcurementOperationState($command->organizationId, $operationId, 'return', $fingerprint, $receipt->id, $command->payload + ['actorId' => $command->actorId, 'returnId' => $return->id]));

    return new ManageProcurementResult('receipt', $this->projection->receipt($receipt, $finance));
  }

  private function reconcileReturn(ManageProcurementCommand $command, bool $finance): ManageProcurementResult
  {
    $this->fields($command->payload, ['clientOperationId']);
    $return = $this->repository->returnDeclaration($command->organizationId, $command->id ?? '') ?? throw ProcurementException::notFound();
    $receipt = $this->repository->receipt($command->organizationId, $return->receiptId) ?? throw ProcurementException::notFound();
    if ('part' === $receipt->kind) {
      $this->access($command, 'organization.inventory.manage');
    }
    $operationId = $this->operationId($command);
    $fingerprint = $this->fingerprint(['returnId' => $return->id]);
    $replay = $this->replay($command, $operationId, 'reconcile_return', $fingerprint, $finance);
    if (null !== $replay) {
      return $replay;
    }
    if ($return->revision !== $this->revision($command)) {
      throw ProcurementException::stale();
    }
    if ('confirmed' !== $return->status) {
      $this->currencies->lock($command->organizationId);
      $movement = $this->stock->tryReverse(new InventoryReceiptReturnRequest($command->organizationId, $receipt->inventoryMovementId ?? throw ProcurementException::conflict('No stock receipt to reverse.'), $return->quantity, $return->reason, $return->clientOperationId, $return->actorId));
      $return->blockedReason = $movement->blockedReason;
      if (null === $movement->blockedReason) {
        $return->status = 'confirmed';
        $return->inventoryMovementId = $movement->movementId;
        $return->reconciledAt = $this->clock->now();
        $receipt->pendingReturnQuantity = DecimalAmount::fromString($receipt->pendingReturnQuantity)->subtract(DecimalAmount::fromString($return->quantity))->toString();
        ++$receipt->revision;
        $this->repository->saveReceipt($receipt);
      }
      ++$return->revision;
      $this->repository->saveReturn($return);
    }
    $this->repository->saveOperation(new ProcurementOperationState($command->organizationId, $operationId, 'reconcile_return', $fingerprint, $receipt->id, $command->payload + ['actorId' => $command->actorId, 'returnId' => $return->id, 'blockedReason' => $return->blockedReason]));

    return new ManageProcurementResult('return', $this->projection->returnDeclaration($return));
  }

  /**
   * @param list<ProcurementLine> $existing
   *
   * @return list<ProcurementLine>
   */
  private function lines(ManageProcurementCommand $command, mixed $input, array $existing): array
  {
    if (!is_array($input) || !array_is_list($input) || count($input) > 100) {
      throw ProcurementException::invalid('Order lines must be a bounded list.');
    }
    $known = [];
    foreach ($existing as $line) {
      $known[$line->id] = $line;
    }
    $partIds = [];
    foreach ($input as $row) {
      if (is_array($row) && 'part' === ($row['kind'] ?? null) && is_string($row['partId'] ?? null)) {
        $partIds[] = $this->uuid($row['partId']);
      }
    }
    $descriptors = $this->parts->describeMany($command->organizationId, array_values(array_unique($partIds)));
    $lines = [];
    foreach ($input as $fields) {
      if (!is_array($fields)) {
        throw ProcurementException::invalid('An order line must be an object.');
      }
      /** @var array<string,mixed> $fields */
      $this->fields($fields, ['id', 'kind', 'partId', 'typeCode', 'identityTemplate', 'quantity', 'unitCost']);
      $id = array_key_exists('id', $fields) ? $this->uuid($this->text($fields, 'id')) : $this->ids->generate();
      $kind = $this->text($fields, 'kind');
      $partId = $this->optionalText($fields, 'partId');
      $partId = null === $partId ? null : $this->uuid($partId);
      $typeCode = $this->optionalText($fields, 'typeCode');
      $descriptor = null === $partId ? null : ($descriptors[$partId] ?? null);
      if ('part' === $kind && (null === $descriptor || $descriptor->archived)) {
        throw ProcurementException::notFound();
      }
      if ('equipment_to_individualize' === $kind && (null === $typeCode || !$this->equipment->supportsType($command->organizationId, $typeCode))) {
        throw ProcurementException::notFound();
      }
      if (array_key_exists('unitCost', $fields)) {
        $this->access($command, 'organization.maintenance_cost.manage');
        $unitCost = $this->optionalText($fields, 'unitCost');
      } else {
        $unitCost = $known[$id]->unitCost ?? null;
      }
      $identity = $fields['identityTemplate'] ?? [];
      if (!is_array($identity) || (!empty($identity) && array_is_list($identity))) {
        throw ProcurementException::invalid('Equipment identity template must be an object.');
      }
      /** @var array<string,mixed> $identity */
      if ('equipment_to_individualize' === $kind) {
        $identity = $this->equipmentTemplate($identity);
      }
      $lines[] = ProcurementLine::create($id, $kind, $partId, $typeCode, $identity, $this->quantity($fields['quantity'] ?? null), $unitCost, $descriptor?->code, $descriptor?->label, $descriptor?->unit);
    }

    return $lines;
  }

  private function access(ManageProcurementCommand $command, string $permission): void
  {
    $decision = $this->authorization->resolveAccess($command->actorId, $command->organizationId, $permission);
    if (OrganizationAccessDecision::GRANTED !== $decision) {
      throw OrganizationAccessDecision::OUTSIDE_SCOPE === $decision ? ProcurementException::notFound() : ProcurementException::denied();
    }
  }

  private function activeSupplier(string $organizationId, string $supplierId): void
  {
    $supplier = $this->repository->supplier($organizationId, $supplierId) ?? throw ProcurementException::notFound();
    if (!$supplier->isActive()) {
      throw ProcurementException::conflict('An archived supplier cannot be selected for a new or changed order.');
    }
  }

  private function revision(ManageProcurementCommand $command): int
  {
    return $command->expectedRevision ?? throw ProcurementException::revisionRequired();
  }

  private function receiptRevision(ManageProcurementCommand $command, ProcurementReceiptState $receipt): void
  {
    if ($this->revision($command) !== $receipt->revision) {
      throw ProcurementException::stale();
    }
  }

  private function lineById(PurchaseOrder $order, string $id): ProcurementLine
  {
    foreach ($order->lines() as $line) {
      if ($line->id === $id) {
        return $line;
      }
    }

    throw ProcurementException::notFound();
  }

  private function operationId(ManageProcurementCommand $command): string
  {
    return $this->uuid($this->text($command->payload, 'clientOperationId'));
  }

  private function replay(ManageProcurementCommand $command, string $operationId, string $kind, string $fingerprint, bool $finance): ?ManageProcurementResult
  {
    $operation = $this->repository->operation($command->organizationId, $operationId);
    if (null === $operation) {
      return null;
    }
    if ($operation->kind !== $kind || $operation->fingerprint !== $fingerprint) {
      throw ProcurementException::conflict('The offline operation identifier already belongs to a different declaration.');
    }
    if ('create_supplier' === $kind) {
      $supplier = $this->repository->supplier($command->organizationId, $operation->receiptId) ?? throw ProcurementException::conflict('The saved creation has no supplier.');

      return new ManageProcurementResult('supplier', $this->projection->supplier($supplier), true);
    }
    if ('create_order' === $kind) {
      $order = $this->repository->order($command->organizationId, $operation->receiptId) ?? throw ProcurementException::conflict('The saved creation has no order.');

      return new ManageProcurementResult('order', $this->projection->order($order, $finance), true);
    }
    if ('reconcile_return' === $kind) {
      $returnId = $operation->declaration['returnId'] ?? null;
      $return = is_string($returnId) ? $this->repository->returnDeclaration($command->organizationId, $returnId) : null;

      return new ManageProcurementResult('return', $this->projection->returnDeclaration($return ?? throw ProcurementException::conflict('The retained return declaration is missing.')), true);
    }
    $receipt = $this->repository->receipt($command->organizationId, $operation->receiptId) ?? throw ProcurementException::conflict('The saved physical operation has no receipt.');

    return new ManageProcurementResult('receipt', $this->projection->receipt($receipt, $finance), true);
  }

  /**
   * @param array<array-key,mixed> $values
   */
  private function fingerprint(array $values): string
  {
    return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
  }

  /**
   * Method creationPayload
   *
   * Keeps object-key order and UUID spelling from changing a retained creation's identity.
   * Generated resource and line UUIDs are deliberately absent from the submitted fingerprint.
   *
   * @access private
   *
   * @param array<array-key,mixed> $payload the submitted creation values
   *
   * @return array<array-key,mixed> the deterministic submitted values
   */
  private function creationPayload(array $payload): array
  {
    foreach ($payload as $field => $value) {
      if (is_array($value)) {
        $payload[$field] = $this->creationPayload($value);
      } elseif (is_string($value) && in_array($field, ['id', 'supplierId', 'partId'], true)) {
        $payload[$field] = $this->uuid($value);
      }
    }
    if (!array_is_list($payload)) {
      ksort($payload);
    }

    return $payload;
  }

  /**
   * @param array<string,mixed> $payload
   * @param list<string> $allowed
   */
  private function fields(array $payload, array $allowed): void
  {
    foreach ($payload as $field => $value) {
      if (!in_array($field, $allowed, true)) {
        throw ProcurementException::invalid('Unknown procurement field: ' . $field);
      }
    }
  }

  /**
   * @param array<string,mixed> $payload
   */
  private function text(array $payload, string $field): string
  {
    $value = $payload[$field] ?? null;
    if (!is_string($value)) {
      throw ProcurementException::invalid('Field ' . $field . ' must be a string.');
    }

    return $value;
  }

  /**
   * @param array<string,mixed> $payload
   */
  private function optionalText(array $payload, string $field): ?string
  {
    if (!array_key_exists($field, $payload) || null === $payload[$field]) {
      return null;
    }

    return $this->text($payload, $field);
  }

  /**
   * @param array<string,mixed> $payload
   */
  private function textOr(array $payload, string $field, string $fallback): string
  {
    return array_key_exists($field, $payload) ? $this->text($payload, $field) : $fallback;
  }

  /**
   * @param array<string,mixed> $payload
   */
  private function optionalOr(array $payload, string $field, ?string $fallback): ?string
  {
    return array_key_exists($field, $payload) ? $this->optionalText($payload, $field) : $fallback;
  }

  private function quantity(mixed $quantity): string
  {
    if (!is_string($quantity)) {
      throw ProcurementException::invalid('Quantity must be an exact decimal string.');
    }
    $value = DecimalAmount::fromString($quantity);
    if ($value->isNegative() || $value->isZero() || $value->compareTo(DecimalAmount::fromInt(100000)) > 0) {
      throw ProcurementException::invalid('Quantity must be positive and at most 100000.');
    }

    return $value->toString();
  }

  private function uuid(string $id): string
  {
    return strtolower(new Uuid($id)->value);
  }

  private function date(string $value): DateTimeImmutable
  {
    if (1 !== preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?(?:Z|[+-]\\d{2}:\\d{2})$/', $value)) {
      throw ProcurementException::invalid('An explicit-offset receivedAt timestamp is required.');
    }

    try {
      $date = new DateTimeImmutable($value);
    } catch (DateMalformedStringException) {
      throw ProcurementException::invalid('The receivedAt date is invalid.');
    }
    $errors = DateTimeImmutable::getLastErrors();
    if (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
      throw ProcurementException::invalid('The receivedAt date is invalid.');
    }

    return $date;
  }

  /**
   * @return list<array{name:string,email:?string,phone:?string,role:?string}>
   */
  private function contacts(mixed $input): array
  {
    if (!is_array($input) || !array_is_list($input)) {
      throw ProcurementException::invalid('Supplier contacts must be a list.');
    }
    $contacts = [];
    foreach ($input as $row) {
      if (!is_array($row)) {
        throw ProcurementException::invalid('A supplier contact must be an object.');
      }
      /** @var array<string,mixed> $row */
      $this->fields($row, ['name', 'email', 'phone', 'role']);
      $contacts[] = ['name' => $this->text($row, 'name'), 'email' => $this->optionalText($row, 'email'), 'phone' => $this->optionalText($row, 'phone'), 'role' => $this->optionalText($row, 'role')];
    }

    return $contacts;
  }

  /**
   * @param array<string,mixed> $input
   *
   * @return array{name?:?string,brand?:?string,model?:?string,subType?:?string,serialNumber?:?string,assetCode?:?string,criticality?:?string,technicalProperties?:list<array{key:string,value:string,unit:?string}>}
   */
  private function equipmentTemplate(array $input): array
  {
    $this->fields($input, ['name', 'brand', 'model', 'subType', 'serialNumber', 'assetCode', 'criticality', 'technicalProperties']);
    $output = ['name' => $this->optionalText($input, 'name'), 'brand' => $this->optionalText($input, 'brand'), 'model' => $this->optionalText($input, 'model'), 'subType' => $this->optionalText($input, 'subType'), 'serialNumber' => $this->optionalText($input, 'serialNumber'), 'assetCode' => $this->optionalText($input, 'assetCode'), 'criticality' => $this->optionalText($input, 'criticality')];
    if (array_key_exists('technicalProperties', $input)) {
      $properties = $input['technicalProperties'];
      if (!is_array($properties) || !array_is_list($properties)) {
        throw ProcurementException::invalid('Equipment technical properties must be a list.');
      }
      $rows = [];
      foreach ($properties as $property) {
        if (!is_array($property)) {
          throw ProcurementException::invalid('Equipment properties must be objects.');
        }
        /** @var array<string,mixed> $property */
        $this->fields($property, ['key', 'value', 'unit']);
        $rows[] = ['key' => $this->text($property, 'key'), 'value' => $this->text($property, 'value'), 'unit' => $this->optionalText($property, 'unit')];
      }
      $output['technicalProperties'] = $rows;
    }

    return $output;
  }
}
