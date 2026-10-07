<?php

declare(strict_types=1);

namespace Inventory\Application\UseCase\Command\ManageInventoryReference;

use InvalidArgumentException;
use Inventory\Application\Port\Outbound\InventoryStorePort;
use Inventory\Domain\Exception\InventoryNotFoundException;
use Inventory\Domain\Model\Stock\InventoryReference;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Domain\ValueObject\Uuid;

use function in_array;
use function trim;

/** @category UseCase */
final readonly class ManageInventoryReferenceHandler implements CommandHandler
{
  public function __construct(private InventoryStorePort $store, private TransactionManagerPort $transactions, private \Shared\Application\Port\Outbound\UuidGeneratorPort $ids)
  {
  }

  public function __invoke(ManageInventoryReferenceCommand $command): ManageInventoryReferenceResult
  {
    new Uuid($command->organizationId);
    if (!in_array($command->type, ['parts', 'warehouses'], true)) {
      throw new InvalidArgumentException('Invalid inventory reference type.');
    }

    return $this->transactions->transactional(function () use ($command): ManageInventoryReferenceResult {
      $existing = null;
      if (null !== $command->id) {
        new Uuid($command->id);
        $existing = $this->store->reference($command->type, $command->organizationId, $command->id, true);
        if (null === $existing) {
          throw new InventoryNotFoundException('Inventory reference not found.');
        }
      }
      $reference = null === $existing
        ? new InventoryReference($this->ids->generate(), $command->organizationId, trim($command->code ?? ''), trim($command->label ?? ''), 'parts' === $command->type ? $command->unit ?? 'piece' : null, 'parts' === $command->type ? $command->kind ?? 'part' : null, $command->archived ?? false)
        : new InventoryReference($existing->id, $command->organizationId, $existing->code, trim($command->label ?? $existing->label), 'parts' === $command->type ? $command->unit ?? $existing->unit : null, $existing->kind, $command->archived ?? $existing->archived);
      $this->store->saveReference($command->type, $reference);

      return new ManageInventoryReferenceResult($reference);
    });
  }
}
