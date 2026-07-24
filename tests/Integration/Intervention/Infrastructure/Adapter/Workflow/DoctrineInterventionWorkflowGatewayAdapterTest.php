<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Workflow;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Adapter\Workflow\DoctrineInterventionWorkflowGatewayAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test DoctrineInterventionWorkflowGatewayAdapterTest.
 *
 * Proves the `name` filter of `list('intervention', ...)` is pushed down
 * into SQL through the shared TrigramSearchExpression builder, fixing the
 * latent bug where an unescaped `%`/`_` in the search term was interpreted
 * as a SQL LIKE wildcard instead of a literal character.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrineInterventionWorkflowGatewayAdapter::class)]
final class DoctrineInterventionWorkflowGatewayAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655449000';

  private const string LITERAL_MATCH_ID = '660e8400-e29b-41d4-a716-446655449010';

  private const string WILDCARD_DECOY_ID = '660e8400-e29b-41d4-a716-446655449011';

  private const string UNRELATED_ID = '660e8400-e29b-41d4-a716-446655449012';

  private EntityManagerInterface $entityManager;

  private DoctrineInterventionWorkflowGatewayAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var DoctrineInterventionWorkflowGatewayAdapter $adapter */
    $adapter = static::getContainer()->get(DoctrineInterventionWorkflowGatewayAdapter::class);
    $this->adapter = $adapter;

    $this->createOrganization();
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testListInterventionFiltersByNameTreatingUnderscoreLiterallyNotAsWildcard(): void
  {
    // Contains a literal underscore, which the `name` filter must match.
    $this->createIntervention(self::LITERAL_MATCH_ID, 'a_b Fire Panel Check', 1);
    // Would ALSO match the buggy, unescaped pattern "%a_b%" because SQL LIKE
    // treats "_" as a single-character wildcard -- this is precisely the
    // latent bug TrigramSearchExpression must not reintroduce.
    $this->createIntervention(self::WILDCARD_DECOY_ID, 'axb Fire Panel Check', 2);
    $this->createIntervention(self::UNRELATED_ID, 'Routine Sprinkler Test', 3);

    $this->entityManager->flush();
    $this->entityManager->clear();

    $page = $this->adapter->list('intervention', self::ORGANIZATION_ID, ['name' => 'a_b'], 1, 20);

    self::assertSame(1, $page->total);
    $ids = array_map(static fn ($view) => $view->data['id'], $page->items);
    self::assertSame([self::LITERAL_MATCH_ID], $ids);
  }

  #[Test]
  public function testListInterventionNameFilterIsCaseInsensitiveAndPartial(): void
  {
    $this->createIntervention(self::LITERAL_MATCH_ID, 'Annual Fire Panel Inspection', 1);
    $this->createIntervention(self::UNRELATED_ID, 'Routine Sprinkler Test', 2);

    $this->entityManager->flush();
    $this->entityManager->clear();

    $page = $this->adapter->list('intervention', self::ORGANIZATION_ID, ['name' => 'FIRE PANEL'], 1, 20);

    self::assertSame(1, $page->total);
    self::assertSame(self::LITERAL_MATCH_ID, $page->items[0]->data['id']);
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Intervention Workflow Search Test';
    $organization->slug = 'intervention-workflow-search-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655449900';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655449900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createIntervention(string $id, string $name, int $number): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $record = new InterventionRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->type = 'site_setup';
    $record->name = $name;
    $record->number = $number;
    $record->status = 'draft';
    $record->priority = 'normal';
    $record->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
  }

  private function cleanup(): void
  {
    foreach ([self::LITERAL_MATCH_ID, self::WILDCARD_DECOY_ID, self::UNRELATED_ID] as $interventionId) {
      $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId);
      if ($intervention instanceof InterventionRecord) {
        $this->entityManager->remove($intervention);
      }
    }
    $this->entityManager->flush();

    $organization = $this->entityManager->find(OrganizationRecord::class, self::ORGANIZATION_ID);
    if ($organization instanceof OrganizationRecord) {
      $this->entityManager->remove($organization);
      $this->entityManager->flush();
    }
  }
}
