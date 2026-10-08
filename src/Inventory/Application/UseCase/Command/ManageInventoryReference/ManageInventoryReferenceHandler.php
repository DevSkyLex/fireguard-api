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
    Uuid::assertValid($command->organizationId);
    if (!in_array($command->type, ['parts', 'warehouses'], true)) {
      throw new InvalidArgumentException('Invalid inventory reference type.');
    }

    return $this->transactions->transactional(function () use ($command): ManageInventoryReferenceResult {
      $existing = null;
      if (null !== $command->id) {
        Uuid::assertValid($command->id);
        $existing = $this->store->reference($command->type, $command->organizationId, $command->id, true);
        if (null === $existing) {
          throw new InventoryNotFoundException('Inventory reference not found.');
        }
      }
      $reference = null === $existing ? $this->createReference($command) : $this->patchReference($command, $existing);
      $this->store->saveReference($command->type, $reference);

      return new ManageInventoryReferenceResult($reference);
    });
  }

  /**
   * Method createReference
   *
   * Applies the part defaults only to a newly created part reference.
   *
   * @access private
   *
   * @param ManageInventoryReferenceCommand $command the validated catalog command
   *
   * @return InventoryReference the new part or warehouse
   */
  private function createReference(ManageInventoryReferenceCommand $command): InventoryReference
  {
    $unit = null;
    $kind = null;
    if ('parts' === $command->type) {
      $unit = $command->unit ?? 'piece';
      $kind = $command->kind ?? 'part';
    }

    return new InventoryReference($this->ids->generate(), $command->organizationId, trim($command->code ?? ''), trim($command->label ?? ''), $unit, $kind, $command->archived ?? false);
  }

  /**
   * Method patchReference
   *
   * Preserves the existing reference identity, code and kind for catalog patches.
   *
   * @access private
   *
   * @param ManageInventoryReferenceCommand $command the validated catalog patch
   * @param InventoryReference $existing the locked owned reference
   *
   * @return InventoryReference the patched part or warehouse
   */
  private function patchReference(ManageInventoryReferenceCommand $command, InventoryReference $existing): InventoryReference
  {
    $unit = 'parts' === $command->type ? $command->unit ?? $existing->unit : null;

    return new InventoryReference($existing->id, $command->organizationId, $existing->code, trim($command->label ?? $existing->label), $unit, $existing->kind, $command->archived ?? $existing->archived);
  }
}
