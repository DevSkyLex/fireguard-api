<?php

declare(strict_types=1);

namespace Inventory\Application\UseCase\Command\ApplyInventoryStock;

use DateTimeImmutable;
use Intervention\Application\Port\Inbound\InterventionInventoryContextPort;
use InvalidArgumentException;
use Inventory\Application\Contract\Stock\{InventoryOperationReceipt,InventoryReceiptResult};
use Inventory\Application\Port\Outbound\InventoryStorePort;
use Inventory\Domain\Exception\{InventoryConflictException,InventoryNotFoundException};
use Inventory\Domain\Model\Stock\{ConsumptionDeclaration, InventoryReference, StockBalance, StockMovement, StockValuation};
use Inventory\Domain\ValueObject\StockQuantity;
use LogicException;
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Domain\ValueObject\{DecimalAmount,Uuid};

use function hash;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function mb_strlen;
use function strtolower;
use function trim;

use const JSON_THROW_ON_ERROR;

/** Atomically retains physical declarations, full stock issues, valuation and immutable replay snapshots. @category UseCase */
final readonly class ApplyInventoryStockHandler implements CommandHandler
{
  public function __construct(private InventoryStorePort $store, private TransactionManagerPort $transactions, private \Shared\Application\Port\Outbound\UuidGeneratorPort $ids, private MaintenanceCurrencyPort $currency, private InterventionInventoryContextPort $interventions)
  {
  }

  public function __invoke(ApplyInventoryStockCommand $command): ApplyInventoryStockResult
  {
    $command = $this->canonical($command);
    new Uuid($command->organizationId);
    new Uuid($command->actorId);
    if (!in_array($command->kind, ['receipt', 'receipt_return', 'consumption', 'return', 'correction', 'reconcile'], true)) {
      throw new InvalidArgumentException('Unknown inventory operation.');
    }
    if ('reconcile' === $command->kind) {
      return $this->transactions->transactional(fn (): ApplyInventoryStockResult => $this->reconcile($command));
    }
    if (null === $command->clientOperationId) {
      throw new InvalidArgumentException('A stable clientOperationId is required.');
    }
    new Uuid($command->clientOperationId);
    $quantity = new StockQuantity($command->quantity ?? '', 'correction' === $command->kind)->value;
    $hash = hash('sha256', json_encode(['kind' => $command->kind, 'partId' => $command->partId, 'warehouseId' => $command->warehouseId, 'quantity' => $quantity, 'occurredAt' => $command->occurredAt?->format('c'), 'interventionId' => $command->interventionId, 'workItemId' => $command->workItemId, 'equipmentId' => $command->equipmentId, 'reason' => $command->reason, 'unitCost' => null === $command->unitCost ? null : $this->amount($command->unitCost), 'currency' => $command->currency, 'sourceReceiptId' => $command->sourceReceiptId, 'originalId' => $command->originalId], JSON_THROW_ON_ERROR));

    return $this->transactions->transactional(function () use ($command, $quantity, $hash): ApplyInventoryStockResult {
      $receipt = $this->store->operationForUpdate($command->organizationId, $command->clientOperationId ?? throw new LogicException('Missing operation identity.'));
      if (null !== $receipt) {
        if ($receipt->payloadHash !== $hash) {
          throw new InventoryConflictException('clientOperationId was already used for another declaration.');
        }
        if ('consumption' === $command->kind) {
          $this->interventions->validate($command->organizationId, $command->interventionId ?? throw new InvalidArgumentException('An intervention is required.'), $command->workItemId, $command->equipmentId, $command->actorId);
        } elseif ('return' === $command->kind) {
          $declaration = $this->store->declaration($command->organizationId, $command->originalId ?? throw new InvalidArgumentException('An original consumption is required.')) ?? throw new InventoryNotFoundException('Consumption declaration not found.');
          $this->interventions->validate($command->organizationId, $declaration->interventionId, $declaration->workItemId, $declaration->equipmentId, $command->actorId);
        }

        return $this->replay($command, $receipt);
      }
      $result = match($command->kind) {
        'receipt' => $this->receive($command, $quantity),
        'consumption' => $this->consume($command, $quantity),
        'return','receipt_return' => $this->returnStock($command, $quantity),
        default => $this->correct($command, $quantity),
      };
      if (null !== $result->receipt?->blockedReason) {
        return $result;
      }
      $this->store->saveOperation(new InventoryOperationReceipt($command->organizationId, $command->clientOperationId ?? throw new LogicException('Missing operation identity.'), $hash, $this->snapshot($result)));

      return $result;
    });
  }

  private function consume(ApplyInventoryStockCommand $command, string $quantity): ApplyInventoryStockResult
  {
    $org = $command->organizationId;
    $intervention = $command->interventionId ?? throw new InvalidArgumentException('An interventionId is required.');
    foreach ([$intervention, $command->workItemId, $command->equipmentId] as $id) {
      if (null !== $id) {
        new Uuid($id);
      }
    }
    $context = $this->interventions->validate($org, $intervention, $command->workItemId, $command->equipmentId, $command->actorId);
    [$part,$warehouse] = $this->references($org, $command->partId, $command->warehouseId);
    $occurred = $command->occurredAt ?? throw new InvalidArgumentException('An occurredAt date is required.');
    $declaration = new ConsumptionDeclaration($this->ids->generate(), $org, $part->id, $warehouse->id, $quantity, $intervention, $command->workItemId, $command->equipmentId, $command->actorId, $occurred, 'received_pending', null, null, $context->published);
    // Persist the complete physical fact before its resolution. Insufficient stock returns normally.
    $this->store->saveDeclaration($declaration);

    return new ApplyInventoryStockResult(declaration:$this->resolve($declaration, $part, $warehouse, $context->published));
  }

  private function canonical(ApplyInventoryStockCommand $c): ApplyInventoryStockCommand
  {
    return new ApplyInventoryStockCommand(strtolower($c->organizationId), strtolower($c->actorId), $c->kind, null === $c->clientOperationId ? null : strtolower($c->clientOperationId), null === $c->partId ? null : strtolower($c->partId), null === $c->warehouseId ? null : strtolower($c->warehouseId), $c->quantity, $c->occurredAt, null === $c->interventionId ? null : strtolower($c->interventionId), null === $c->workItemId ? null : strtolower($c->workItemId), null === $c->equipmentId ? null : strtolower($c->equipmentId), $c->reason, $c->unitCost, $c->currency, null === $c->sourceReceiptId ? null : strtolower($c->sourceReceiptId), null === $c->originalId ? null : strtolower($c->originalId));
  }

  private function resolve(ConsumptionDeclaration $declaration, InventoryReference $part, InventoryReference $warehouse, bool $late): ConsumptionDeclaration
  {
    $currency = $this->currency->lock($declaration->organizationId);
    $balance = $this->store->balanceForUpdate($declaration->organizationId, $warehouse->id, $part->id);
    $reason = match(true) {
      $part->archived || $warehouse->archived => 'archived_reference',null === $balance => 'missing_balance',DecimalAmount::fromString($balance->quantity)->compareTo(DecimalAmount::fromString($declaration->quantity)) < 0 => 'insufficient_stock',default => null
    };
    if (null !== $reason) {
      $pending = $this->resolvedDeclaration($declaration, 'received_pending', $reason, null, $late);
      $this->store->saveDeclaration($pending);

      return $pending;
    }
    if (null === $balance) {
      throw new LogicException('Missing locked balance.');
    }
    if ($currency !== $balance->currency) {
      throw new InventoryConflictException('Inventory balance currency does not match the organization.');
    }
    $issue = new StockValuation($balance->quantity, $balance->totalValue)->issue($declaration->quantity);
    $movement = new StockMovement($this->ids->generate(), $declaration->organizationId, $part->id, $warehouse->id, 'consumption', $this->negative($declaration->quantity), $issue->unitCost, null === $issue->totalValue ? null : $this->negative($issue->totalValue), $currency, 'Declared intervention consumption', $declaration->actorId, $declaration->occurredAt, $declaration->interventionId, $declaration->workItemId, $declaration->equipmentId, null, null, $late);
    $this->store->saveBalance(new StockBalance($balance->id, $balance->organizationId, $balance->partId, $balance->warehouseId, $issue->balance->quantity, $issue->balance->totalValue, $currency));
    $this->store->saveMovement($movement);
    $confirmed = $this->resolvedDeclaration($declaration, 'confirmed', null, $movement->id, $late);
    $this->store->saveDeclaration($confirmed);

    return $confirmed;
  }

  private function reconcile(ApplyInventoryStockCommand $command): ApplyInventoryStockResult
  {
    $id = $command->originalId ?? throw new InvalidArgumentException('A declarationId is required.');
    new Uuid($id);
    // Parent scope is read before locking declaration: advisory parent -> declaration -> balance, same order as publication.
    $candidate = $this->store->declaration($command->organizationId, $id);
    if (null === $candidate) {
      throw new InventoryNotFoundException('Consumption declaration not found.');
    }
    $context = $this->interventions->validate($command->organizationId, $candidate->interventionId, $candidate->workItemId, $candidate->equipmentId, $command->actorId);
    $declaration = $this->store->declaration($command->organizationId, $id, true) ?? throw new InventoryNotFoundException('Consumption declaration not found.');
    if ('confirmed' === $declaration->status) {
      return new ApplyInventoryStockResult(declaration:$declaration, replayed:true);
    }
    [$part,$warehouse] = $this->references($command->organizationId, $declaration->partId, $declaration->warehouseId);

    return new ApplyInventoryStockResult(declaration:$this->resolve($declaration, $part, $warehouse, $context->published || $declaration->late));
  }

  private function receive(ApplyInventoryStockCommand $command, string $quantity): ApplyInventoryStockResult
  {
    [$part,$warehouse] = $this->references($command->organizationId, $command->partId, $command->warehouseId);
    if ($part->archived || $warehouse->archived) {
      throw new InventoryConflictException('Archived inventory references cannot receive stock.');
    }
    $currency = $this->currency->lock($command->organizationId);
    if ($command->currency !== $currency) {
      throw new InventoryConflictException('Receipt currency must match the organization currency.');
    }
    $unit = null === $command->unitCost ? null : $this->amount($command->unitCost);
    $value = null === $unit ? null : DecimalAmount::fromString($unit)->multiply(DecimalAmount::fromString($quantity))->toString();
    $balance = $this->store->balanceForUpdate($command->organizationId, $warehouse->id, $part->id);
    $valuation = new StockValuation(null === $balance ? '0.000000' : $balance->quantity, null === $balance ? '0.000000' : $balance->totalValue)->receive($quantity, $value);
    $movement = new StockMovement($this->ids->generate(), $command->organizationId, $part->id, $warehouse->id, 'receipt', $quantity, $unit, $value, $currency, 'Procurement receipt', $command->actorId, new DateTimeImmutable(), sourceReceiptId:$command->sourceReceiptId);
    $this->store->saveBalance(new StockBalance(null === $balance ? $this->ids->generate() : $balance->id, $command->organizationId, $part->id, $warehouse->id, $valuation->quantity, $valuation->totalValue, $currency));
    $this->store->saveMovement($movement);

    return new ApplyInventoryStockResult(movement:$movement, receipt:new InventoryReceiptResult($movement->id, $quantity, $unit, $value));
  }

  private function returnStock(ApplyInventoryStockCommand $command, string $quantity): ApplyInventoryStockResult
  {
    $reason = $this->reason($command->reason);
    $originalId = $command->originalId ?? throw new InvalidArgumentException('The original declaration or movement is required.');
    new Uuid($originalId);
    $org = $command->organizationId;
    $late = false;
    if ('return' === $command->kind) {
      $declaration = $this->store->declaration($org, $originalId);
      if (null === $declaration) {
        throw new InventoryNotFoundException('Consumption declaration not found.');
      }
      $context = $this->interventions->validate($org, $declaration->interventionId, $declaration->workItemId, $declaration->equipmentId, $command->actorId);
      $late = $context->published;
      if (null === $declaration->movementId) {
        throw new InventoryConflictException('An unresolved declaration cannot be returned to stock.');
      }
      $originalId = $declaration->movementId;
    }
    $original = $this->store->movement($org, $originalId) ?? throw new InventoryNotFoundException('Stock movement not found.');
    if (('return' === $command->kind && 'consumption' !== $original->kind) || ('receipt_return' === $command->kind && 'receipt' !== $original->kind)) {
      throw new InventoryConflictException('The referenced movement is not returnable.');
    }
    [$part,$warehouse] = $this->references($org, $original->partId, $original->warehouseId);
    $currency = $this->currency->lock($org);
    $balance = $this->store->balanceForUpdate($org, $warehouse->id, $part->id) ?? throw new InventoryConflictException('Missing inventory balance.');
    $returned = DecimalAmount::fromString($this->store->linkedQuantity($org, $originalId));
    $originalQuantity = DecimalAmount::fromString($original->quantity);
    if ($originalQuantity->isNegative()) {
      $originalQuantity = DecimalAmount::zero()->subtract($originalQuantity);
    }
    $cumulativeQuantity = $returned->add(DecimalAmount::fromString($quantity));
    if ($cumulativeQuantity->compareTo($originalQuantity) > 0) {
      throw new InventoryConflictException('Returns cannot exceed the original quantity.');
    }
    if ('receipt_return' === $command->kind && DecimalAmount::fromString($balance->quantity)->compareTo(DecimalAmount::fromString($quantity)) < 0) {
      return new ApplyInventoryStockResult(receipt:new InventoryReceiptResult('', $quantity, null, null, blockedReason:'stock_insufficient'));
    }
    $unit = $original->unitCost;
    $originalValue = null === $original->totalValue ? null : DecimalAmount::fromString($original->totalValue);
    if (null !== $originalValue && $originalValue->isNegative()) {
      $originalValue = DecimalAmount::zero()->subtract($originalValue);
    }
    // Allocate from the remaining original value so rounding cannot overcredit partial returns.
    $lastReturn = 0 === $cumulativeQuantity->compareTo($originalQuantity);
    $linkedValue = $this->store->linkedValue($org, $originalId);
    $value = null;
    if ('return' === $command->kind && null !== $originalValue && null !== $linkedValue) {
      $remainingValue = $originalValue->subtract(DecimalAmount::fromString($linkedValue));
      if ($remainingValue->isNegative()) {
        throw new InventoryConflictException('Original stock valuation is inconsistent.');
      }
      $value = $lastReturn ? $remainingValue->toString() : $remainingValue->multiplyAndDivide(DecimalAmount::fromString($quantity), $originalQuantity->subtract($returned))->toString();
    }
    if ('return' === $command->kind && null !== $value && DecimalAmount::fromString($value)->isNegative()) {
      throw new InventoryConflictException('Original stock valuation is inconsistent.');
    }
    $sign = 'return' === $command->kind ? 1 : -1;
    $valuation = new StockValuation($balance->quantity, $balance->totalValue);
    if (1 === $sign) {
      $next = $valuation->receive($quantity, $value);
    } else {
      $issue = $valuation->issue($quantity);
      $next = $issue->balance;
      $value = $issue->totalValue;
      $unit = $issue->unitCost;
    }
    $movement = new StockMovement($this->ids->generate(), $org, $part->id, $warehouse->id, $command->kind, 1 === $sign ? $quantity : $this->negative($quantity), $unit, null === $value ? null : (1 === $sign ? $value : $this->negative($value)), $currency, $reason, $command->actorId, new DateTimeImmutable(), $original->interventionId, $original->workItemId, $original->equipmentId, $originalId, $original->sourceReceiptId, $late);
    $this->store->saveBalance(new StockBalance($balance->id, $org, $part->id, $warehouse->id, $next->quantity, $next->totalValue, $currency));
    $this->store->saveMovement($movement);

    return new ApplyInventoryStockResult(movement:$movement, receipt:new InventoryReceiptResult($movement->id, $quantity, $unit, $value));
  }

  private function correct(ApplyInventoryStockCommand $command, string $quantity): ApplyInventoryStockResult
  {
    $reason = $this->reason($command->reason);
    [$part,$warehouse] = $this->references($command->organizationId, $command->partId, $command->warehouseId);
    $currency = $this->currency->lock($command->organizationId);
    $balance = $this->store->balanceForUpdate($command->organizationId, $warehouse->id, $part->id);
    $valuation = new StockValuation(null === $balance ? '0.000000' : $balance->quantity, null === $balance ? '0.000000' : $balance->totalValue);
    $delta = DecimalAmount::fromString($quantity);
    $value = null;
    $unit = null;
    if ($delta->isNegative()) {
      $issue = $valuation->issue(DecimalAmount::zero()->subtract($delta)->toString());
      $next = $issue->balance;
      $unit = $issue->unitCost;
      $value = null === $issue->totalValue ? null : $this->negative($issue->totalValue);
    } else {
      $unit = null === $command->unitCost ? null : $this->amount($command->unitCost);
      $value = null === $unit ? null : DecimalAmount::fromString($unit)->multiply($delta)->toString();
      $next = $valuation->receive($quantity, $value);
    }
    $movement = new StockMovement($this->ids->generate(), $command->organizationId, $part->id, $warehouse->id, 'correction', $quantity, $unit, $value, $currency, $reason, $command->actorId, $command->occurredAt ?? new DateTimeImmutable());
    $this->store->saveBalance(new StockBalance(null === $balance ? $this->ids->generate() : $balance->id, $command->organizationId, $part->id, $warehouse->id, $next->quantity, $next->totalValue, $currency));
    $this->store->saveMovement($movement);

    return new ApplyInventoryStockResult(movement:$movement);
  }

  /**
   * @return array{InventoryReference,InventoryReference}
   */
  private function references(string $org, ?string $partId, ?string $warehouseId): array
  {
    if (null === $partId || null === $warehouseId) {
      throw new InvalidArgumentException('Part and warehouse identifiers are required.');
    }new Uuid($partId);
    new Uuid($warehouseId);
    $part = $this->store->reference('parts', $org, $partId, true) ?? throw new InventoryNotFoundException('Inventory reference not found.');
    $warehouse = $this->store->reference('warehouses', $org, $warehouseId, true) ?? throw new InventoryNotFoundException('Inventory reference not found.');

    return [$part, $warehouse];
  }

  private function amount(string $value): string
  {
    $amount = DecimalAmount::fromString($value);
    if ($amount->isNegative()) {
      throw new InvalidArgumentException('Unit cost cannot be negative.');
    }

    return $amount->toString();
  }

  private function negative(string $value): string
  {
    return DecimalAmount::zero()->subtract(DecimalAmount::fromString($value))->toString();
  }

  private function reason(?string $value): string
  {
    $value = trim($value ?? '');
    if ('' === $value || mb_strlen($value) > 2000) {
      throw new InvalidArgumentException('A reason between 1 and 2000 characters is required.');
    }

    return $value;
  }

  private function resolvedDeclaration(ConsumptionDeclaration $d, string $status, ?string $reason, ?string $movement, bool $late): ConsumptionDeclaration
  {
    return new ConsumptionDeclaration($d->id, $d->organizationId, $d->partId, $d->warehouseId, $d->quantity, $d->interventionId, $d->workItemId, $d->equipmentId, $d->actorId, $d->occurredAt, $status, $reason, $movement, $late);
  }

  /**
   * @return array<string,mixed>
   */
  private function snapshot(ApplyInventoryStockResult $result): array
  {
    if (null !== $result->declaration) {
      $d = $result->declaration;

      return ['declaration' => ['id' => $d->id, 'organizationId' => $d->organizationId, 'partId' => $d->partId, 'warehouseId' => $d->warehouseId, 'quantity' => $d->quantity, 'interventionId' => $d->interventionId, 'workItemId' => $d->workItemId, 'equipmentId' => $d->equipmentId, 'actorId' => $d->actorId, 'occurredAt' => $d->occurredAt->format('c'), 'status' => $d->status, 'reason' => $d->reason, 'movementId' => $d->movementId, 'late' => $d->late]];
    }
    $m = $result->movement ?? throw new LogicException('Missing stock movement response.');

    return ['movementId' => $m->id, 'receipt' => null === $result->receipt ? null : ['movementId' => $result->receipt->movementId, 'quantity' => $result->receipt->quantity, 'unitCost' => $result->receipt->unitCost, 'totalValue' => $result->receipt->totalValue]];
  }

  private function replay(ApplyInventoryStockCommand $command, InventoryOperationReceipt $receipt): ApplyInventoryStockResult
  {
    $data = $receipt->response;
    if (isset($data['declaration']) && is_array($data['declaration'])) {
      $d = $data['declaration'];

      return new ApplyInventoryStockResult(declaration:new ConsumptionDeclaration($this->s($d, 'id'), $command->organizationId, $this->s($d, 'partId'), $this->s($d, 'warehouseId'), $this->s($d, 'quantity'), $this->s($d, 'interventionId'), $this->n($d, 'workItemId'), $this->n($d, 'equipmentId'), $this->s($d, 'actorId'), new DateTimeImmutable($this->s($d, 'occurredAt')), $this->s($d, 'status'), $this->n($d, 'reason'), $this->n($d, 'movementId'), true === ($d['late'] ?? false)), replayed:true);
    }
    $movement = $this->store->movement($command->organizationId, $this->s($data, 'movementId')) ?? throw new LogicException('Immutable replay movement unavailable.');
    $r = $data['receipt'] ?? null;

    return new ApplyInventoryStockResult(movement:$movement, receipt:is_array($r) ? new InventoryReceiptResult($this->s($r, 'movementId'), $this->s($r, 'quantity'), $this->n($r, 'unitCost'), $this->n($r, 'totalValue'), true) : null, replayed:true);
  }

  /**
   * @param array<mixed> $data
   */
  private function s(array $data, string $key): string
  {
    if (!isset($data[$key]) || !is_string($data[$key])) {
      throw new LogicException('Malformed inventory replay snapshot.');
    }

    return $data[$key];
  }

  /**
   * @param array<mixed> $data
   */
  private function n(array $data, string $key): ?string
  {
    return null === ($data[$key] ?? null) ? null : $this->s($data, $key);
  }
}
