<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\RemoveTagFromEquipment;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Domain\Exception\{EquipmentNotFoundException, TagNotFoundException};
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, TagId};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase RemoveTagFromEquipmentHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveTagFromEquipmentHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private TagRepositoryPort $tagRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(RemoveTagFromEquipmentCommand $command): RemoveTagFromEquipmentResult
  {
    try {
      $equipmentId = EquipmentId::fromString($command->equipmentId);
      $organizationId = EquipmentOrganizationId::fromString($command->organizationId);
      $tagId = TagId::fromString($command->tagId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $equipment = $this->equipmentRepository->findById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($command->equipmentId);
    }

    if (!$this->tagRepository->isTagLinkedToEquipment($equipmentId, $tagId)) {
      throw TagNotFoundException::withId($command->tagId);
    }

    $this->tagRepository->removeTagFromEquipment($equipmentId, $tagId);

    return new RemoveTagFromEquipmentResult(
      equipmentId: (string) $equipmentId,
      tagId: (string) $tagId,
    );
  }
  // #endregion
}
