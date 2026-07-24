<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Label;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Adapter\Label\DoctrineInterventionLabelAdapter;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test DoctrineInterventionLabelAdapter.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrineInterventionLabelAdapter::class)]
final class DoctrineInterventionLabelAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655440001';

  private const string OTHER_ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655440002';

  private EntityManagerInterface $entityManager;

  private DoctrineInterventionLabelAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var UuidGeneratorPort $generator */
    $generator = static::getContainer()->get(UuidGeneratorPort::class);
    $this->adapter = new DoctrineInterventionLabelAdapter($this->entityManager, new UuidFactory($generator));

    $this->createOrganization(self::ORGANIZATION_ID, 'label-adapter-test');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'label-adapter-other');
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testCreateThenFindRoundTripsTheLabel(): void
  {
    $created = $this->adapter->create(self::ORGANIZATION_ID, 'Urgent', '#ff0000');
    $this->entityManager->clear();

    self::assertSame(self::ORGANIZATION_ID, $created->organizationId);
    self::assertSame('Urgent', $created->name);
    self::assertSame('#ff0000', $created->color);

    $found = $this->adapter->find($created->id);
    self::assertNotNull($found);
    self::assertSame($created->id, $found->id);
    self::assertSame('Urgent', $found->name);
    self::assertSame('#ff0000', $found->color);
  }

  #[Test]
  public function testListIsScopedToTheOrganizationAndOrderedByNameAscending(): void
  {
    $this->adapter->create(self::ORGANIZATION_ID, 'Zeta', '#000001');
    $this->adapter->create(self::ORGANIZATION_ID, 'Alpha', '#000002');
    // Belongs to another organization: must never appear in the scoped list.
    $this->adapter->create(self::OTHER_ORGANIZATION_ID, 'Foreign', '#000003');
    $this->entityManager->clear();

    $page = $this->adapter->list(self::ORGANIZATION_ID, 1, 20);

    self::assertSame(2, $page->total);
    $names = array_map(static fn ($view): string => $view->name, $page->items);
    self::assertSame(['Alpha', 'Zeta'], $names);
  }

  #[Test]
  public function testListPaginatesWithinTheOrganization(): void
  {
    $this->adapter->create(self::ORGANIZATION_ID, 'Aaa', '#000001');
    $this->adapter->create(self::ORGANIZATION_ID, 'Bbb', '#000002');
    $this->adapter->create(self::ORGANIZATION_ID, 'Ccc', '#000003');
    $this->entityManager->clear();

    $firstPage = $this->adapter->list(self::ORGANIZATION_ID, 1, 2);
    self::assertSame(3, $firstPage->total);
    self::assertCount(2, $firstPage->items);
    self::assertSame('Aaa', $firstPage->items[0]->name);
    self::assertSame('Bbb', $firstPage->items[1]->name);

    $secondPage = $this->adapter->list(self::ORGANIZATION_ID, 2, 2);
    self::assertSame(3, $secondPage->total);
    self::assertCount(1, $secondPage->items);
    self::assertSame('Ccc', $secondPage->items[0]->name);
  }

  #[Test]
  public function testUpdateAppliesOnlyProvidedFields(): void
  {
    $created = $this->adapter->create(self::ORGANIZATION_ID, 'Draft', '#aaaaaa');
    $this->entityManager->clear();

    $updated = $this->adapter->update($created->id, 'Reviewed', null, hasName: true, hasColor: false);
    $this->entityManager->clear();

    self::assertSame('Reviewed', $updated->name);
    self::assertSame('#aaaaaa', $updated->color);

    $found = $this->adapter->find($created->id);
    self::assertNotNull($found);
    self::assertSame('Reviewed', $found->name);
    self::assertSame('#aaaaaa', $found->color);
  }

  #[Test]
  public function testDeleteRemovesTheLabel(): void
  {
    $created = $this->adapter->create(self::ORGANIZATION_ID, 'Temporary', '#123456');
    $this->entityManager->clear();

    $this->adapter->delete($created->id);
    $this->entityManager->clear();

    self::assertNull($this->adapter->find($created->id));
  }

  #[Test]
  public function testFindReturnsNullWhenUnknown(): void
  {
    self::assertNull($this->adapter->find('880e8400-e29b-41d4-a716-4466554400ff'));
  }

  private function createOrganization(string $id, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Label Adapter ' . $slug;
    $organization->slug = $slug;
    $organization->ownerUserId = '880e8400-e29b-41d4-a716-446655449000';
    $organization->createdByUserId = '880e8400-e29b-41d4-a716-446655449000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM intervention_labels WHERE organization_id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
