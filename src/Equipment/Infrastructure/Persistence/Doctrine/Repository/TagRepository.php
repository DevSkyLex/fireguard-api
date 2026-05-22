<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Equipment\Application\Port\Outbound\TagRepositoryPort;
use Equipment\Domain\Model\Tag\Tag;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, TagId};
use Equipment\Infrastructure\Persistence\Doctrine\Mapper\TagMapper;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentRecord, EquipmentTagRecord, TagRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

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
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $tag->organizationId());
    $record->organization = $organization;
    $existing = $this->tagEntityRepository->find($record->id);

    if ($existing instanceof TagRecord) {
      $existing->organization = $organization;
      $existing->name = $record->name;
      $existing->createdAt = $record->createdAt;
    } else {
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
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $record = $this->tagEntityRepository->findOneBy([
      'name' => $name,
      'organization' => $organization,
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
    /** @var EquipmentRecord $equipment */
    $equipment = $this->entityManager->getReference(EquipmentRecord::class, (string) $equipmentId);
    $qb = $this->entityManager->createQueryBuilder();
    $qb
      ->select('t')
      ->from(TagRecord::class, 't')
      ->innerJoin('t.equipmentLinks', 'et')
      ->where('et.equipment = :equipment')
      ->setParameter('equipment', $equipment);

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
    /** @var EquipmentRecord $equipment */
    $equipment = $this->entityManager->getReference(EquipmentRecord::class, (string) $equipmentId);
    /** @var TagRecord $tag */
    $tag = $this->entityManager->getReference(TagRecord::class, (string) $tagId);

    return null !== $this->pivotEntityRepository->findOneBy([
      'equipment' => $equipment,
      'tag' => $tag,
    ]);
  }

  /**
   * Method addTagToEquipment.
   *
   * @since 1.0.0
   */
  public function addTagToEquipment(EquipmentId $equipmentId, TagId $tagId): void
  {
    /** @var EquipmentRecord $equipment */
    $equipment = $this->entityManager->getReference(EquipmentRecord::class, (string) $equipmentId);
    /** @var TagRecord $tag */
    $tag = $this->entityManager->getReference(TagRecord::class, (string) $tagId);
    $existing = $this->pivotEntityRepository->findOneBy([
      'equipment' => $equipment,
      'tag' => $tag,
    ]);

    if (null !== $existing) {
      return;
    }

    $pivot = new EquipmentTagRecord();
    $pivot->equipment = $equipment;
    $pivot->tag = $tag;

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
    /** @var EquipmentRecord $equipment */
    $equipment = $this->entityManager->getReference(EquipmentRecord::class, (string) $equipmentId);
    /** @var TagRecord $tag */
    $tag = $this->entityManager->getReference(TagRecord::class, (string) $tagId);
    $pivot = $this->pivotEntityRepository->findOneBy([
      'equipment' => $equipment,
      'tag' => $tag,
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
    $qb = $this->entityManager->createQueryBuilder();
    /** @var list<EquipmentTagRecord> $pivots */
    $pivots = $qb
      ->select('et', 't', 'e')
      ->from(EquipmentTagRecord::class, 'et')
      ->innerJoin('et.tag', 't')
      ->innerJoin('et.equipment', 'e')
      ->where($qb->expr()->in('e.id', ':ids'))
      ->setParameter('ids', $stringIds)
      ->getQuery()
      ->getResult();

    if ([] === $pivots) {
      return [];
    }

    $result = [];
    foreach ($pivots as $pivot) {
      if ($pivot->equipment instanceof EquipmentRecord && $pivot->tag instanceof TagRecord) {
        $result[$pivot->equipment->id][] = TagMapper::toDomain($pivot->tag);
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
      /** @var OrganizationRecord $organization */
      $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $tag->organizationId());
      /** @var EquipmentRecord $equipment */
      $equipment = $this->entityManager->getReference(EquipmentRecord::class, (string) $equipmentId);
      $record->organization = $organization;

      $existingRecord = $this->tagEntityRepository->find($record->id);
      if ($existingRecord instanceof TagRecord) {
        $existingRecord->organization = $organization;
        $existingRecord->name = $record->name;
        $existingRecord->createdAt = $record->createdAt;
      } else {
        $this->entityManager->persist($record);
      }

      $existingPivot = $this->pivotEntityRepository->findOneBy([
        'equipment' => $equipment,
        'tag' => $existingRecord instanceof TagRecord ? $existingRecord : $record,
      ]);

      if (null === $existingPivot) {
        $pivot = new EquipmentTagRecord();
        $pivot->equipment = $equipment;
        $pivot->tag = $existingRecord instanceof TagRecord ? $existingRecord : $record;
        $this->entityManager->persist($pivot);
      }
    });
  }

  /**
   * Method findByOrganizationId.
   *
   * @since 1.0.0
   */
  public function findByOrganizationId(EquipmentOrganizationId $organizationId, ?string $search = null): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $qb = $this->entityManager->createQueryBuilder();
    $qb
      ->select('t')
      ->from(TagRecord::class, 't')
      ->where('t.organization = :organization')
      ->setParameter('organization', $organization)
      ->orderBy('t.name', 'ASC');

    if (null !== $search && '' !== $search) {
      $qb
        ->andWhere('LOWER(t.name) LIKE LOWER(:search)')
        ->setParameter('search', '%' . $search . '%');
    }

    /** @var list<TagRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map(
      static fn (TagRecord $record): Tag => TagMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method countByOrganizationId.
   *
   * @since 1.0.0
   */
  public function countByOrganizationId(EquipmentOrganizationId $organizationId, ?string $search = null): int
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $qb = $this->entityManager->createQueryBuilder();
    $qb
      ->select('COUNT(t.id)')
      ->from(TagRecord::class, 't')
      ->where('t.organization = :organization')
      ->setParameter('organization', $organization);

    if (null !== $search && '' !== $search) {
      $qb
        ->andWhere('LOWER(t.name) LIKE LOWER(:search)')
        ->setParameter('search', '%' . $search . '%');
    }

    return (int) $qb->getQuery()->getSingleScalarResult();
  }
  // #endregion
}
