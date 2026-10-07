<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DateMalformedStringException;
use DateTimeImmutable;
use InvalidArgumentException;
use Inventory\Application\UseCase\Command\ApplyInventoryStock\{ApplyInventoryStockCommand,ApplyInventoryStockResult};
use Inventory\Application\UseCase\Command\ManageInventoryReference\{ManageInventoryReferenceCommand,ManageInventoryReferenceResult};
use Inventory\Presentation\Api\Dto\Input\{CorrectInventoryStockInput, CreateInventoryPartInput, CreateInventoryWarehouseInput, DeclareInventoryConsumptionInput, PatchInventoryPartInput, PatchInventoryWarehouseInput, ReconcileInventoryConsumptionInput, ReturnInventoryConsumptionInput};
use Inventory\Presentation\Api\Dto\Output\{InventoryBalanceOutput, InventoryConsumptionOutput, InventoryMovementOutput, InventoryPartOutput, InventoryWarehouseOutput};
use Inventory\Presentation\Api\Service\{InventoryAccess,InventoryOutputFactory};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function is_string;

/**
 * @category Processor
 *
 * @implements ProcessorInterface<object,InventoryPartOutput|InventoryWarehouseOutput|InventoryBalanceOutput|InventoryMovementOutput|InventoryConsumptionOutput>
 */
final readonly class InventoryProcessor implements ProcessorInterface
{
  public function __construct(private CommandBusPort $commands, private InventoryAccess $access, private InventoryOutputFactory $outputs)
  {
  }

  /**
   * @param mixed $data validated input
   * @param array<string,mixed> $uriVariables route identifiers
   * @param array<string,mixed> $context processor context
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InventoryPartOutput|InventoryWarehouseOutput|InventoryBalanceOutput|InventoryMovementOutput|InventoryConsumptionOutput
  {
    $consume = $data instanceof DeclareInventoryConsumptionInput || $data instanceof ReturnInventoryConsumptionInput;
    $org = $this->access->organization($uriVariables, $consume ? 'organization.inventory.consume' : 'organization.inventory.manage');
    $actor = $this->access->actor();
    $id = $uriVariables['id'] ?? null;
    if (null !== $id && !is_string($id)) {
      throw new BadRequestHttpException('Invalid inventory identifier.');
    }
    if ($data instanceof CreateInventoryPartInput || $data instanceof PatchInventoryPartInput || $data instanceof CreateInventoryWarehouseInput || $data instanceof PatchInventoryWarehouseInput) {
      $command = match(true) {
        $data instanceof CreateInventoryPartInput => new ManageInventoryReferenceCommand($org, 'parts', code:$data->code, label:$data->label, unit:$data->unit, kind:$data->kind),
        $data instanceof PatchInventoryPartInput => new ManageInventoryReferenceCommand($org, 'parts', id:$id, label:$data->label, unit:$data->unit, archived:$data->archived),
        $data instanceof CreateInventoryWarehouseInput => new ManageInventoryReferenceCommand($org, 'warehouses', code:$data->code, label:$data->name),
        default => new ManageInventoryReferenceCommand($org, 'warehouses', id:$id, label:$data->name, archived:$data->archived),
      };
      /** @var ManageInventoryReferenceResult $result */
      $result = $this->commands->dispatch($command);

      return $this->outputs->output($result->reference);
    }
    if ($data instanceof CorrectInventoryStockInput) {
      $this->access->requirePermission($org, 'organization.maintenance_cost.manage');
      $command = new ApplyInventoryStockCommand($org, $actor, 'correction', $data->clientOperationId, $data->partId, $data->warehouseId, $data->quantity, reason:$data->reason, unitCost:$data->unitCost);
    } elseif ($data instanceof DeclareInventoryConsumptionInput) {
      $this->access->requirePermission($org, 'organization.interventions.execute');
      $command = new ApplyInventoryStockCommand($org, $actor, 'consumption', $data->clientOperationId, $data->partId, $data->warehouseId, $data->quantity, $this->date($data->occurredAt), $data->interventionId, $data->workItemId, $data->equipmentId);
    } elseif ($data instanceof ReturnInventoryConsumptionInput) {
      $this->access->requirePermission($org, 'organization.interventions.execute');
      $command = new ApplyInventoryStockCommand($org, $actor, 'return', $data->clientOperationId, quantity:$data->quantity, reason:$data->reason, originalId:$data->consumptionId);
    } elseif ($data instanceof ReconcileInventoryConsumptionInput && null !== $id) {
      $this->access->requirePermission($org, 'organization.interventions.execute');
      $command = new ApplyInventoryStockCommand($org, $actor, 'reconcile', originalId:$id);
    } else {
      throw new BadRequestHttpException('Invalid inventory input.');
    }
    /** @var ApplyInventoryStockResult $result */
    $result = $this->commands->dispatch($command);

    return $this->outputs->output($result->declaration ?? $result->movement ?? throw new BadRequestHttpException('Inventory result unavailable.'), $this->access->financial($org), $result->replayed);
  }

  private function date(string $value): DateTimeImmutable
  {
    try {
      $date = new DateTimeImmutable($value);
    } catch (DateMalformedStringException $error) {
      throw new InvalidArgumentException('Invalid physical occurrence date.', previous:$error);
    }
    $errors = DateTimeImmutable::getLastErrors();
    if (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
      throw new InvalidArgumentException('Invalid physical occurrence date.');
    }

    return $date;
  }
}
