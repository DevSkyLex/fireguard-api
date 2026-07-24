<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Domain\Model\Tag\Tag;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, TagId};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Infrastructure\Persistence\Doctrine\Repository\TagRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test TagRepositoryTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TagRepository::class)]
final class TagRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-4466554b0001';

  private const string OTHER_ORGANIZATION_ID = '660e8400-e29b-41d4-a716-4466554b0002';

  private const string EQUIPMENT_ID = '660e8400-e29b-41d4-a716-4466554b0003';

  private EntityManagerInterface $entityManager;

  private TagRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new TagRepository($this->entityManager);

    $this->createOrganization(self::ORGANIZATION_ID, 'Tag Repository Test', 'tag-repository-test');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Tag Repository Other', 'tag-repository-other');
    $this->createEquipment();
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveAndFindByIdRoundTrip(): void
  {
    $id = TagId::fromString('660e8400-e29b-41d4-a716-4466554b0010');
    $tag = Tag::reconstitute(
      id: $id,
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Critical',
      createdAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
    );

    $this->repository->save($tag);
    $this->entityManager->clear();

    $found = $this->repository->findById($id);

    self::assertInstanceOf(Tag::class, $found);
    self::assertSame('Critical', $found->name());
    self::assertSame(self::ORGANIZATION_ID, (string) $found->organizationId());
  }

  #[Test]
  public function testFindByIdReturnsNullWhenMissing(): void
  {
    self::assertNull(
      $this->repository->findById(TagId::fromString('660e8400-e29b-41d4-a716-4466554b00ff')),
    );
  }

  #[Test]
  public function testFindByNameAndOrganizationIdIsScopedByOrganization(): void
  {
    $this->repository->save(Tag::reconstitute(
      id: TagId::fromString('660e8400-e29b-41d4-a716-4466554b0020'),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Shared Name',
      createdAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
    ));
    $this->repository->save(Tag::reconstitute(
      id: TagId::fromString('660e8400-e29b-41d4-a716-4466554b0021'),
      organizationId: EquipmentOrganizationId::fromString(self::OTHER_ORGANIZATION_ID),
      name: 'Shared Name',
      createdAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
    ));
    $this->entityManager->clear();

    $found = $this->repository->findByNameAndOrganizationId(
      'Shared Name',
      EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
    );

    self::assertInstanceOf(Tag::class, $found);
    self::assertSame('660e8400-e29b-41d4-a716-4466554b0020', (string) $found->id());
    self::assertNull(
      $this->repository->findByNameAndOrganizationId(
        'Missing Name',
        EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      ),
    );
  }

  #[Test]
  public function testAddIsLinkedAndRemoveTagRoundTrip(): void
  {
    $tagId = TagId::fromString('660e8400-e29b-41d4-a716-4466554b0030');
    $equipmentId = EquipmentId::fromString(self::EQUIPMENT_ID);
    $this->repository->save(Tag::reconstitute(
      id: $tagId,
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Linkable',
      createdAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
    ));

    self::assertFalse($this->repository->isTagLinkedToEquipment($equipmentId, $tagId));

    $this->repository->addTagToEquipment($equipmentId, $tagId);
    // Idempotent add must not create a duplicate pivot.
    $this->repository->addTagToEquipment($equipmentId, $tagId);

    self::assertTrue($this->repository->isTagLinkedToEquipment($equipmentId, $tagId));

    $linkedTags = $this->repository->findByEquipmentId($equipmentId);
    self::assertCount(1, $linkedTags);
    self::assertSame('Linkable', $linkedTags[0]->name());

    $this->repository->removeTagFromEquipment($equipmentId, $tagId);

    self::assertFalse($this->repository->isTagLinkedToEquipment($equipmentId, $tagId));
    self::assertCount(0, $this->repository->findByEquipmentId($equipmentId));
  }

  #[Test]
  public function testSaveAndLinkToEquipmentPersistsBothTagAndPivot(): void
  {
    $tagId = TagId::fromString('660e8400-e29b-41d4-a716-4466554b0040');
    $equipmentId = EquipmentId::fromString(self::EQUIPMENT_ID);

    $this->repository->saveAndLinkToEquipment(
      Tag::reconstitute(
        id: $tagId,
        organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
        name: 'Atomic',
        createdAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      ),
      $equipmentId,
    );
    $this->entityManager->clear();

    self::assertInstanceOf(Tag::class, $this->repository->findById($tagId));
    self::assertTrue($this->repository->isTagLinkedToEquipment($equipmentId, $tagId));
  }

  #[Test]
  public function testFindTagsByEquipmentIdsGroupsByEquipment(): void
  {
    $tagId = TagId::fromString('660e8400-e29b-41d4-a716-4466554b0050');
    $equipmentId = EquipmentId::fromString(self::EQUIPMENT_ID);
    $this->repository->saveAndLinkToEquipment(
      Tag::reconstitute(
        id: $tagId,
        organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
        name: 'Grouped',
        createdAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      ),
      $equipmentId,
    );
    $this->entityManager->clear();

    $grouped = $this->repository->findTagsByEquipmentIds([$equipmentId]);

    self::assertArrayHasKey(self::EQUIPMENT_ID, $grouped);
    self::assertCount(1, $grouped[self::EQUIPMENT_ID]);
    self::assertSame('Grouped', $grouped[self::EQUIPMENT_ID][0]->name());
    self::assertSame([], $this->repository->findTagsByEquipmentIds([]));
  }

  #[Test]
  public function testFindByOrganizationIdFiltersSearchAndCounts(): void
  {
    $this->repository->save(Tag::reconstitute(
      id: TagId::fromString('660e8400-e29b-41d4-a716-4466554b0060'),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Alpha',
      createdAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
    ));
    $this->repository->save(Tag::reconstitute(
      id: TagId::fromString('660e8400-e29b-41d4-a716-4466554b0061'),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Beta',
      createdAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
    ));
    // Same-named tag in another organization must be excluded.
    $this->repository->save(Tag::reconstitute(
      id: TagId::fromString('660e8400-e29b-41d4-a716-4466554b0062'),
      organizationId: EquipmentOrganizationId::fromString(self::OTHER_ORGANIZATION_ID),
      name: 'Alpha',
      createdAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
    ));
    $this->entityManager->clear();

    $orgId = EquipmentOrganizationId::fromString(self::ORGANIZATION_ID);

    $all = $this->repository->findByOrganizationId($orgId);
    self::assertCount(2, $all);
    self::assertSame('Alpha', $all[0]->name());
    self::assertSame('Beta', $all[1]->name());
    self::assertSame(2, $this->repository->countByOrganizationId($orgId));

    $filtered = $this->repository->findByOrganizationId($orgId, 'alph');
    self::assertCount(1, $filtered);
    self::assertSame('Alpha', $filtered[0]->name());
    self::assertSame(1, $this->repository->countByOrganizationId($orgId, 'alph'));
  }

  private function createOrganization(string $id, string $name, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-4466554b9000';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-4466554b9000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createEquipment(): void
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;
    $equipment->organization = $organization;
    $equipment->type = 'fire_extinguisher';
    $equipment->status = 'operational';
    $equipment->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $equipment->updatedAt = $equipment->createdAt;
    $this->entityManager->persist($equipment);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM equipment_tag WHERE equipment_id = :equipmentId',
      ['equipmentId' => self::EQUIPMENT_ID],
    );
    $connection->executeStatement(
      'DELETE FROM equipment_tag_catalog WHERE organization_id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM equipment WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
