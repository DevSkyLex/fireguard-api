<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Equipment\Application\Port\Outbound\TagRepositoryPort;
use Equipment\Domain\Model\Tag\Tag;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, TagId};
use Equipment\Infrastructure\Persistence\Doctrine\Mapper\TagMapper;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentTagRecord, TagRecord};

use function array_keys;
use function array_map;

/**
 * Repository TagRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TagRepository implements TagRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<TagRecord>
   */
  private EntityRepository $tagEntityRepository;

  /**
   * @var EntityRepository<EquipmentTagRecord>
   */
  private EntityRepository $pivotEntityRepository;
  // #endregion

  // #region Constructor
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->tagEntityRepository = $this->entityManager->getRepository(TagRecord::class);
    $this->pivotEntityRepository = $this->entityManager->getRepository(EquipmentTagRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * @since 1.0.0
   */
  public function save(Tag $tag): void
  {
    $record = TagMapper::toRecord($tag);
    $existing = $this->tagEntityRepository->find($record->id);

    if (!$existing instanceof TagRecord) {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById.
   *
   * @since 1.0.0
   */
  public function findById(TagId $id): ?Tag
  {
    $record = $this->tagEntityRepository->find((string) $id);

    if (!$record instanceof TagRecord) {
      return null;
    }

    return TagMapper::toDomain($record);
  }

  /**
   * Method findByNameAndOrganizationId.
   *
   * @since 1.0.0
   */
  public function findByNameAndOrganizationId(string $name, EquipmentOrganizationId $organizationId): ?Tag
  {
    $record = $this->tagEntityRepository->findOneBy([
      'name' => $name,
      'organizationId' => (string) $organizationId,
    ]);

    if (!$record instanceof TagRecord) {
      return null;
    }

    return TagMapper::toDomain($record);
  }

  /**
   * Method findByEquipmentId.
   *
   * @since 1.0.0
   */
  public function findByEquipmentId(EquipmentId $equipmentId): array
  {
    $qb = $this->entityManager->createQueryBuilder();
    $qb
      ->select('t')
      ->from(TagRecord::class, 't')
      ->innerJoin(EquipmentTagRecord::class, 'et', 'WITH', 'et.tagId = t.id AND et.equipmentId = :equipmentId')
      ->setParameter('equipmentId', (string) $equipmentId);

    /** @var list<TagRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map(
      static fn (TagRecord $record): Tag => TagMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method isTagLinkedToEquipment.
   *
   * @since 1.0.0
   */
  public function isTagLinkedToEquipment(EquipmentId $equipmentId, TagId $tagId): bool
  {
    return null !== $this->pivotEntityRepository->findOneBy([
      'equipmentId' => (string) $equipmentId,
      'tagId' => (string) $tagId,
    ]);
  }

  /**
   * Method addTagToEquipment.
   *
   * @since 1.0.0
   */
  public function addTagToEquipment(EquipmentId $equipmentId, TagId $tagId): void
  {
    $existing = $this->pivotEntityRepository->findOneBy([
      'equipmentId' => (string) $equipmentId,
      'tagId' => (string) $tagId,
    ]);

    if (null !== $existing) {
      return;
    }

    $pivot = new EquipmentTagRecord();
    $pivot->equipmentId = (string) $equipmentId;
    $pivot->tagId = (string) $tagId;

    $this->entityManager->persist($pivot);
    $this->entityManager->flush();
  }

  /**
   * Method removeTagFromEquipment.
   *
   * @since 1.0.0
   */
  public function removeTagFromEquipment(EquipmentId $equipmentId, TagId $tagId): void
  {
    $pivot = $this->pivotEntityRepository->findOneBy([
      'equipmentId' => (string) $equipmentId,
      'tagId' => (string) $tagId,
    ]);

    if (null === $pivot) {
      return;
    }

    $this->entityManager->remove($pivot);
    $this->entityManager->flush();
  }

  /**
   * Method findTagsByEquipmentIds.
   *
   * @since 1.0.0
   */
  public function findTagsByEquipmentIds(array $equipmentIds): array
  {
    if ([] === $equipmentIds) {
      return [];
    }

    $stringIds = array_map(static fn (EquipmentId $id): string => (string) $id, $equipmentIds);

    /** @var list<EquipmentTagRecord> $pivots */
    $pivots = $this->pivotEntityRepository->findBy(['equipmentId' => $stringIds]);

    if ([] === $pivots) {
      return [];
    }

    $tagIdSet = [];
    foreach ($pivots as $pivot) {
      $tagIdSet[$pivot->tagId] = true;
    }

    /** @var list<TagRecord> $tagRecords */
    $tagRecords = $this->tagEntityRepository->findBy(['id' => array_keys($tagIdSet)]);

    $tagsById = [];
    foreach ($tagRecords as $tagRecord) {
      $tagsById[$tagRecord->id] = TagMapper::toDomain($tagRecord);
    }

    $result = [];
    foreach ($pivots as $pivot) {
      if (isset($tagsById[$pivot->tagId])) {
        $result[$pivot->equipmentId][] = $tagsById[$pivot->tagId];
      }
    }

    return $result;
  }

  /**
   * Method saveAndLinkToEquipment.
   *
   * @since 1.0.0
   */
  public function saveAndLinkToEquipment(Tag $tag, EquipmentId $equipmentId): void
  {
    $this->entityManager->wrapInTransaction(function () use ($tag, $equipmentId): void {
      $record = TagMapper::toRecord($tag);

      if (!$this->tagEntityRepository->find($record->id) instanceof TagRecord) {
        $this->entityManager->persist($record);
      }

      $existingPivot = $this->pivotEntityRepository->findOneBy([
        'equipmentId' => (string) $equipmentId,
        'tagId' => (string) $tag->id(),
      ]);

      if (null === $existingPivot) {
        $pivot = new EquipmentTagRecord();
        $pivot->equipmentId = (string) $equipmentId;
        $pivot->tagId = (string) $tag->id();
        $this->entityManager->persist($pivot);
      }
    });
  }
  // #endregion
}
