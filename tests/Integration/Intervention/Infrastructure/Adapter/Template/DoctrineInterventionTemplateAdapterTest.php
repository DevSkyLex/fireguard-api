<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Template;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Infrastructure\Adapter\Template\DoctrineInterventionTemplateAdapter;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test DoctrineInterventionTemplateAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrineInterventionTemplateAdapter::class)]
final class DoctrineInterventionTemplateAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'aa0e8400-e29b-41d4-a716-446655440001';

  private const string OTHER_ORGANIZATION_ID = 'aa0e8400-e29b-41d4-a716-446655440002';

  private EntityManagerInterface $entityManager;

  private DoctrineInterventionTemplateAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var UuidGeneratorPort $generator */
    $generator = static::getContainer()->get(UuidGeneratorPort::class);
    $this->adapter = new DoctrineInterventionTemplateAdapter($this->entityManager, new UuidFactory($generator));

    $this->createOrganization(self::ORGANIZATION_ID, 'template-adapter-test');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'template-adapter-other');
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
  public function testCreateThenFindRoundTripsTheTemplateWithItemsInPositionOrder(): void
  {
    $created = $this->adapter->create(
      self::ORGANIZATION_ID,
      'Annual audit',
      'Yearly fire safety audit',
      'inspection_campaign',
      'high',
      null,
      null,
      'P14D',
      ['aa0e8400-e29b-41d4-a716-446655440aaa'],
      [
        ['action' => 'Inspect extinguishers', 'target' => null, 'resultResource' => null, 'required' => true, 'defaultAssigneeId' => null],
        ['action' => 'Check alarms', 'target' => null, 'resultResource' => null, 'required' => false, 'defaultAssigneeId' => null],
      ],
    );
    $this->entityManager->clear();

    self::assertSame(self::ORGANIZATION_ID, $created->organizationId);
    self::assertSame('Annual audit', $created->name);
    self::assertSame('inspection_campaign', $created->type);
    self::assertSame('high', $created->priority);
    self::assertSame('P14D', $created->duration);

    $found = $this->adapter->find($created->id);
    self::assertNotNull($found);
    self::assertSame($created->id, $found->id);
    self::assertSame('Yearly fire safety audit', $found->description);
    self::assertSame(['aa0e8400-e29b-41d4-a716-446655440aaa'], $found->labelIds);
    self::assertCount(2, $found->items);
    $actions = array_map(static fn ($item): string => $item->action, $found->items);
    self::assertSame(['Inspect extinguishers', 'Check alarms'], $actions);
    self::assertSame(0, $found->items[0]->position);
    self::assertSame(1, $found->items[1]->position);
  }

  #[Test]
  public function testListIsScopedToTheOrganizationAndOrderedByNameAscending(): void
  {
    $this->createTemplate('Zeta');
    $this->createTemplate('Alpha');
    // Belongs to another organization: must never appear in the scoped list.
    $this->createTemplate('Foreign', self::OTHER_ORGANIZATION_ID);
    $this->entityManager->clear();

    $page = $this->adapter->list(self::ORGANIZATION_ID, 1, 20);

    self::assertSame(2, $page->total);
    $names = array_map(static fn ($view): string => $view->name, $page->items);
    self::assertSame(['Alpha', 'Zeta'], $names);
  }

  #[Test]
  public function testListFiltersByCaseInsensitiveNameSearch(): void
  {
    $this->createTemplate('Fire Safety Audit');
    $this->createTemplate('Electrical Review');
    $this->entityManager->clear();

    $page = $this->adapter->list(self::ORGANIZATION_ID, 1, 20, 'fire');

    self::assertSame(1, $page->total);
    self::assertCount(1, $page->items);
    self::assertSame('Fire Safety Audit', $page->items[0]->name);
  }

  #[Test]
  public function testUpdateAppliesOnlyProvidedFields(): void
  {
    $created = $this->adapter->create(
      self::ORGANIZATION_ID,
      'Draft template',
      'Original description',
      'site_setup',
      'normal',
      null,
      null,
      null,
      [],
      [],
    );
    $this->entityManager->clear();

    $updated = $this->adapter->update(
      $created->id,
      'Renamed template',
      null,
      null,
      'high',
      null,
      null,
      null,
      null,
      null,
      hasName: true,
      hasDescription: false,
      hasType: false,
      hasPriority: true,
      hasDefaultSiteId: false,
      hasDefaultResponsibleId: false,
      hasDuration: false,
      hasLabelIds: false,
      hasItems: false,
    );
    $this->entityManager->clear();

    self::assertSame('Renamed template', $updated->name);
    self::assertSame('high', $updated->priority);
    // Untouched fields must be preserved.
    self::assertSame('Original description', $updated->description);
    self::assertSame('site_setup', $updated->type);

    $found = $this->adapter->find($created->id);
    self::assertNotNull($found);
    self::assertSame('Renamed template', $found->name);
    self::assertSame('Original description', $found->description);
  }

  #[Test]
  public function testDeleteRemovesTheTemplate(): void
  {
    $created = $this->adapter->create(
      self::ORGANIZATION_ID,
      'Temporary template',
      null,
      'site_setup',
      'normal',
      null,
      null,
      null,
      [],
      [],
    );
    $this->entityManager->clear();

    $this->adapter->delete($created->id);
    $this->entityManager->clear();

    self::assertNull($this->adapter->find($created->id));
  }

  #[Test]
  public function testCreateRejectsDuplicateNameWithinTheOrganization(): void
  {
    $this->adapter->create(
      self::ORGANIZATION_ID,
      'Unique name',
      null,
      'site_setup',
      'normal',
      null,
      null,
      null,
      [],
      [],
    );
    $this->entityManager->clear();

    $this->expectException(InterventionConflictException::class);
    $this->adapter->create(
      self::ORGANIZATION_ID,
      'Unique name',
      null,
      'site_setup',
      'normal',
      null,
      null,
      null,
      [],
      [],
    );
  }

  #[Test]
  public function testFindReturnsNullWhenUnknown(): void
  {
    self::assertNull($this->adapter->find('aa0e8400-e29b-41d4-a716-4466554400ff'));
  }

  private function createTemplate(string $name, string $organizationId = self::ORGANIZATION_ID): void
  {
    $this->adapter->create(
      $organizationId,
      $name,
      null,
      'site_setup',
      'normal',
      null,
      null,
      null,
      [],
      [],
    );
  }

  private function createOrganization(string $id, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Template Adapter ' . $slug;
    $organization->slug = $slug;
    $organization->ownerUserId = 'aa0e8400-e29b-41d4-a716-446655449000';
    $organization->createdByUserId = 'aa0e8400-e29b-41d4-a716-446655449000';
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
      'DELETE FROM intervention_template_items WHERE template_id IN (SELECT id FROM intervention_templates WHERE organization_id IN (:organizationIds))',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_templates WHERE organization_id IN (:organizationIds)',
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
