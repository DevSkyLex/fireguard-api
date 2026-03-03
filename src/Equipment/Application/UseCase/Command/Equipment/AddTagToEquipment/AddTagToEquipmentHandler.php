<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\AddTagToEquipment;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\Tag\Tag;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, TagId};
use InvalidArgumentException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;

use function mb_strtolower;
use function trim;

/**
 * UseCase AddTagToEquipmentHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddTagToEquipmentHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private TagRepositoryPort $tagRepository,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(AddTagToEquipmentCommand $command): AddTagToEquipmentResult
  {
    try {
      $equipmentId = EquipmentId::fromString($command->equipmentId);
      $organizationId = EquipmentOrganizationId::fromString($command->organizationId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $equipment = $this->equipmentRepository->findById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($command->equipmentId);
    }

    $normalizedName = mb_strtolower(trim($command->tagName));

    $tag = $this->tagRepository->findByNameAndOrganizationId($normalizedName, $organizationId);

    if (null === $tag) {
      /** @var TagId $tagId */
      $tagId = $this->uuidFactory->create(TagId::class);
      $tag = Tag::create($tagId, $organizationId, $normalizedName);
      $this->tagRepository->saveAndLinkToEquipment($tag, $equipmentId);
    } else {
      $this->tagRepository->addTagToEquipment($equipmentId, $tag->id());
    }

    return new AddTagToEquipmentResult(
      tagId: (string) $tag->id(),
      tagName: $tag->name(),
      organizationId: (string) $tag->organizationId(),
    );
  }
  // #endregion
}
