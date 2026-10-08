<?php

declare(strict_types=1);

namespace Procurement\Application\UseCase\Command\ManageProcurement;

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
use Procurement\Application\Service\{ProcurementDraftLines, ProcurementInput, ProcurementOperationReplay, ProcurementProjection};
use Procurement\Domain\Event\ProcurementChangedEvent;
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Domain\Model\{PurchaseOrder, Supplier};
use Procurement\Domain\ValueObject\{ProcurementLine, SupplierDetails};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, UuidGeneratorPort};
use Shared\Domain\ValueObject\DecimalAmount;

use function array_key_exists;
use function count;
use function is_string;
use function strlen;
use function strtolower;
use function trim;

/** Reserves remaining quantities and stock receipts atomically in the main database. */
final readonly class ManageProcurementHandler implements CommandHandler
{
  /**
   * Property input
   */
  private ProcurementInput $input;

  /**
   * Property draftLines
   */
  private ProcurementDraftLines $draftLines;

  /**
   * Property operations
   */
  private ProcurementOperationReplay $operations;

  /**
   * Method __construct
   *
   * @access public
   *
   * @param ProcurementRepositoryPort $repository the repository value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param MaintenanceCurrencyPort $currencies the currencies value
   * @param InventoryPartDirectoryPort $parts the parts value
   * @param InventoryStockReceiptPort $stock the stock value
   * @param EquipmentReserveReceiptPort $equipment the equipment value
   * @param ProcurementProjection $projection the projection value
   * @param ClockPort $clock the clock value
   * @param UuidGeneratorPort $ids the ids value
   * @param EventDispatcherPort $events the events value
   *
   * @return void
   */
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
    $this->input = new ProcurementInput();
    $this->draftLines = new ProcurementDraftLines($authorization, $parts, $equipment, $ids, $this->input);
    $this->operations = new ProcurementOperationReplay($repository, $projection, $this->input);
  }

  /**
   * Method __invoke
   *
   * Runs each authorized mutation and its retained operation under one organization lock.
   *
   * @access public
   *
   * @param ManageProcurementCommand $command the command value
   *
   * @return ManageProcurementResult
   */
  public function __invoke(ManageProcurementCommand $command): ManageProcurementResult
  {
    $command = new ManageProcurementCommand(strtolower($command->actorId), strtolower($command->organizationId), $command->action, null === $command->id ? null : strtolower($command->id), $command->expectedRevision, $command->payload);
    $this->access($command, 'organization.procurement.manage');
    if ('individualize' === $command->action) {
      $this->access($command, 'organization.equipment.write');
    }
    $finance = $this->authorization->hasPermission($command->actorId, $command->organizationId, 'organization.maintenance_cost.read');

    return $this->repository->synchronized($command->organizationId, function () use ($command, $finance): ManageProcurementResult {
      $replay = $this->operations->creationReplay($command, $finance);
      if (null !== $replay) {
        return $replay;
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
      $this->operations->recordCreation($command, $result);
      if (!$result->replayed) {
        $resourceId = $result->data['id'] ?? null;
        if (is_string($resourceId)) {
          $this->events->dispatch(new ProcurementChangedEvent($command->organizationId, $resourceId, $command->action, $this->clock->now()));
        }
      }

      return $result;
    });
  }

  /**
   * Method supplier
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   *
   * @return ManageProcurementResult
   */
  private function supplier(ManageProcurementCommand $command): ManageProcurementResult
  {
    $this->input->fields($command->payload, 'create_supplier' === $command->action ? ['name', 'code', 'email', 'phone', 'contacts', 'clientOperationId'] : ['name', 'code', 'email', 'phone', 'contacts']);
    $now = $this->clock->now();
    if ('create_supplier' === $command->action) {
      $supplier = Supplier::create($this->ids->generate(), $command->organizationId, new SupplierDetails($this->input->text($command->payload, 'name'), $this->input->optionalText($command->payload, 'code'), $this->input->optionalText($command->payload, 'email'), $this->input->optionalText($command->payload, 'phone'), $this->input->contacts($command->payload['contacts'] ?? [])), $now);
    } else {
      $supplier = $this->repository->supplier($command->organizationId, $command->id ?? '') ?? throw ProcurementException::notFound();
      $revision = $this->revision($command);
      if ('archive_supplier' === $command->action) {
        $supplier->archive($revision, $now);
      } else {
        $supplier->change($revision, $this->input->textOr($command->payload, 'name', $supplier->name()), $this->input->optionalOr($command->payload, 'code', $supplier->code()), $this->input->optionalOr($command->payload, 'email', $supplier->email()), $this->input->optionalOr($command->payload, 'phone', $supplier->phone()), $this->input->contacts($command->payload['contacts'] ?? $supplier->contacts()), $now);
      }
    }
    $this->repository->saveSupplier($supplier);

    return new ManageProcurementResult('supplier', $this->projection->supplier($supplier));
  }

  /**
   * Method order
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   * @param bool $finance the finance value
   *
   * @return ManageProcurementResult
   */
  private function order(ManageProcurementCommand $command, bool $finance): ManageProcurementResult
  {
    $this->input->fields($command->payload, 'create_order' === $command->action ? ['name', 'supplierId', 'lines', 'clientOperationId'] : ['name', 'supplierId', 'lines']);
    $now = $this->clock->now();
    if ('create_order' === $command->action) {
      $supplierId = $this->input->uuid($this->input->text($command->payload, 'supplierId'));
      $this->activeSupplier($command->organizationId, $supplierId);
      $order = PurchaseOrder::create($this->ids->generate(), $command->organizationId, $supplierId, $this->currencies->lock($command->organizationId), $this->input->text($command->payload, 'name'), $this->draftLines->prepare($command, $command->payload['lines'] ?? [], []), $now);
    } else {
      $order = $this->repository->order($command->organizationId, $command->id ?? '') ?? throw ProcurementException::notFound();
      $revision = $this->revision($command);
      if ('order' === $command->action) {
        $this->activeSupplier($command->organizationId, $order->supplierId());
        $order->order($revision, $now);
      } elseif ('cancel_remaining' === $command->action) {
        $order->cancelRemaining($revision, $now);
      } else {
        $supplierId = $this->input->uuid($this->input->textOr($command->payload, 'supplierId', $order->supplierId()));
        $this->activeSupplier($command->organizationId, $supplierId);
        $lines = array_key_exists('lines', $command->payload) ? $this->draftLines->prepare($command, $command->payload['lines'], $order->lines()) : $order->lines();
        $order->changeDraft($revision, $supplierId, $order->currency(), $this->input->textOr($command->payload, 'name', $order->name()), $lines, $now);
      }
    }
    $this->repository->saveOrder($order);

    return new ManageProcurementResult('order', $this->projection->order($order, $finance));
  }

  /**
   * Method receive
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   * @param bool $finance the finance value
   *
   * @return ManageProcurementResult
   */
  private function receive(ManageProcurementCommand $command, bool $finance): ManageProcurementResult
  {
    $this->input->fields($command->payload, ['lineId', 'warehouseId', 'quantity', 'receivedAt', 'clientOperationId']);
    $order = $this->repository->order($command->organizationId, $command->id ?? '') ?? throw ProcurementException::notFound();
    $lineId = $this->input->uuid($this->input->text($command->payload, 'lineId'));
    $line = $this->lineById($order, $lineId);
    if ('part' === $line->kind) {
      $this->access($command, 'organization.inventory.manage');
    }
    $quantity = $this->input->quantity($command->payload['quantity'] ?? null);
    $warehouseId = $this->input->optionalText($command->payload, 'warehouseId');
    $warehouseId = null === $warehouseId ? null : $this->input->uuid($warehouseId);
    $receivedAt = $this->input->date($this->input->text($command->payload, 'receivedAt'));
    if ($receivedAt > $this->clock->now()) {
      throw ProcurementException::invalid('A physical receipt cannot occur in the future.');
    }
    $operationId = $this->operations->operationId($command);
    $fingerprint = $this->operations->fingerprint(['orderId' => $order->id, 'lineId' => $lineId, 'quantity' => $quantity, 'warehouseId' => $warehouseId, 'receivedAt' => $receivedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP')]);
    $replay = $this->operations->replay($command, $operationId, 'receive', $fingerprint, $finance);
    if (null !== $replay) {
      return $replay;
    }
    if ('equipment_to_individualize' === $line->kind && (null !== $warehouseId || !DecimalAmount::fromString($quantity)->isInteger() || DecimalAmount::fromString($quantity)->compareTo(DecimalAmount::fromInt(100)) > 0)) {
      throw ProcurementException::invalid('Equipment receipts contain at most 100 units and no quantitative warehouse stock.');
    }
    if ('part' === $line->kind && (null === $warehouseId || !$this->parts->warehouseExistsActive($command->organizationId, $this->input->uuid($warehouseId)))) {
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

  /**
   * Method individualize
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   * @param bool $finance the finance value
   *
   * @return ManageProcurementResult
   */
  private function individualize(ManageProcurementCommand $command, bool $finance): ManageProcurementResult
  {
    $this->input->fields($command->payload, ['clientOperationId']);
    $receipt = $this->repository->receipt($command->organizationId, $command->id ?? '') ?? throw ProcurementException::notFound();
    $operationId = $this->operations->operationId($command);
    $fingerprint = $this->operations->fingerprint(['receiptId' => $receipt->id]);
    $replay = $this->operations->replay($command, $operationId, 'individualize', $fingerprint, $finance);
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
      $result = $this->equipment->reserve(new EquipmentReserveReceiptRequest($command->organizationId, $command->actorId, $line->typeCode ?? throw ProcurementException::invalid('Missing equipment type.'), $this->input->equipmentTemplate($line->identityTemplate), $remaining->toInt()));
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

  /**
   * Method returnReceipt
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   * @param bool $finance the finance value
   *
   * @return ManageProcurementResult
   */
  private function returnReceipt(ManageProcurementCommand $command, bool $finance): ManageProcurementResult
  {
    $this->input->fields($command->payload, ['quantity', 'reason', 'clientOperationId']);
    $receipt = $this->repository->receipt($command->organizationId, $command->id ?? '') ?? throw ProcurementException::notFound();
    $quantity = $this->input->quantity($command->payload['quantity'] ?? null);
    if ('part' === $receipt->kind) {
      $this->access($command, 'organization.inventory.manage');
    }
    $reason = trim($this->input->text($command->payload, 'reason'));
    if ('' === $reason || strlen($reason) > 2000) {
      throw ProcurementException::invalid('A motivated return reason is required.');
    }
    $operationId = $this->operations->operationId($command);
    $fingerprint = $this->operations->fingerprint(['receiptId' => $receipt->id, 'quantity' => $quantity, 'reason' => $reason]);
    $replay = $this->operations->replay($command, $operationId, 'return', $fingerprint, $finance);
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

  /**
   * Method reconcileReturn
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   * @param bool $finance the finance value
   *
   * @return ManageProcurementResult
   */
  private function reconcileReturn(ManageProcurementCommand $command, bool $finance): ManageProcurementResult
  {
    $this->input->fields($command->payload, ['clientOperationId']);
    $return = $this->repository->returnDeclaration($command->organizationId, $command->id ?? '') ?? throw ProcurementException::notFound();
    $receipt = $this->repository->receipt($command->organizationId, $return->receiptId) ?? throw ProcurementException::notFound();
    if ('part' === $receipt->kind) {
      $this->access($command, 'organization.inventory.manage');
    }
    $operationId = $this->operations->operationId($command);
    $fingerprint = $this->operations->fingerprint(['returnId' => $return->id]);
    $replay = $this->operations->replay($command, $operationId, 'reconcile_return', $fingerprint, $finance);
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
   * Method access
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   * @param string $permission the permission value
   *
   * @return void
   */
  private function access(ManageProcurementCommand $command, string $permission): void
  {
    $decision = $this->authorization->resolveAccess($command->actorId, $command->organizationId, $permission);
    if (OrganizationAccessDecision::GRANTED !== $decision) {
      throw OrganizationAccessDecision::OUTSIDE_SCOPE === $decision ? ProcurementException::notFound() : ProcurementException::denied();
    }
  }

  /**
   * Method activeSupplier
   *
   * @access private
   *
   * @param string $organizationId the organizationId value
   * @param string $supplierId the supplierId value
   *
   * @return void
   */
  private function activeSupplier(string $organizationId, string $supplierId): void
  {
    $supplier = $this->repository->supplier($organizationId, $supplierId) ?? throw ProcurementException::notFound();
    if (!$supplier->isActive()) {
      throw ProcurementException::conflict('An archived supplier cannot be selected for a new or changed order.');
    }
  }

  /**
   * Method revision
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   *
   * @return int
   */
  private function revision(ManageProcurementCommand $command): int
  {
    return $command->expectedRevision ?? throw ProcurementException::revisionRequired();
  }

  /**
   * Method receiptRevision
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   * @param ProcurementReceiptState $receipt the receipt value
   *
   * @return void
   */
  private function receiptRevision(ManageProcurementCommand $command, ProcurementReceiptState $receipt): void
  {
    if ($this->revision($command) !== $receipt->revision) {
      throw ProcurementException::stale();
    }
  }

  /**
   * Method lineById
   *
   * @access private
   *
   * @param PurchaseOrder $order the order value
   * @param string $id the id value
   *
   * @return ProcurementLine
   */
  private function lineById(PurchaseOrder $order, string $id): ProcurementLine
  {
    foreach ($order->lines() as $line) {
      if ($line->id === $id) {
        return $line;
      }
    }

    throw ProcurementException::notFound();
  }
}
