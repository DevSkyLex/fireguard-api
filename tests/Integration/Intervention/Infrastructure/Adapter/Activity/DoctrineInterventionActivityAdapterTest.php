<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Activity;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Adapter\Activity\DoctrineInterventionActivityAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function is_string;

/**
 * Test DoctrineInterventionActivityAdapter.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrineInterventionActivityAdapter::class)]
final class DoctrineInterventionActivityAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '990e8400-e29b-41d4-a716-446655440001';

  private const string INTERVENTION_ID = '990e8400-e29b-41d4-a716-446655440002';

  private const string OTHER_INTERVENTION_ID = '990e8400-e29b-41d4-a716-446655440003';

  private const string ACTOR_ID = '990e8400-e29b-41d4-a716-446655440009';

  private EntityManagerInterface $entityManager;

  private DoctrineInterventionActivityAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var DoctrineInterventionActivityAdapter $adapter */
    $adapter = static::getContainer()->get(DoctrineInterventionActivityAdapter::class);
    $this->adapter = $adapter;

    $this->createOrganization();
    $this->createIntervention(self::INTERVENTION_ID, 1);
    $this->createIntervention(self::OTHER_INTERVENTION_ID, 2);
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
  public function testAppendPersistsAnActivityReadableViaListByIntervention(): void
  {
    $view = $this->adapter->append(
      self::INTERVENTION_ID,
      self::ORGANIZATION_ID,
      self::ACTOR_ID,
      'comment',
      'comment',
      'Panel inspected, all good.',
      ['channel' => 'web'],
    );
    $this->entityManager->clear();

    self::assertSame('activity', $view->resource);
    self::assertSame(self::ORGANIZATION_ID, $view->organizationId);
    self::assertSame('comment', $view->data['kind']);
    self::assertSame('Panel inspected, all good.', $view->data['body']);
    self::assertSame(['channel' => 'web'], $view->data['payload']);

    $page = $this->adapter->listByIntervention(self::INTERVENTION_ID, 1, 20);
    self::assertSame(1, $page->total);
    self::assertSame($view->data['id'], $page->items[0]->data['id']);
    self::assertSame('/api/interventions/' . self::INTERVENTION_ID, $page->items[0]->data['intervention']);
  }

  #[Test]
  public function testListByInterventionIsScopedAndOrderedByCreatedAtAscending(): void
  {
    $first = $this->adapter->append(self::INTERVENTION_ID, self::ORGANIZATION_ID, null, 'system', 'created', null, null);
    $second = $this->adapter->append(self::INTERVENTION_ID, self::ORGANIZATION_ID, self::ACTOR_ID, 'comment', 'comment', 'Second note', null);
    $third = $this->adapter->append(self::INTERVENTION_ID, self::ORGANIZATION_ID, self::ACTOR_ID, 'comment', 'comment', 'Third note', null);
    // Belongs to another intervention: must be excluded from the scoped feed.
    $this->adapter->append(self::OTHER_INTERVENTION_ID, self::ORGANIZATION_ID, null, 'system', 'created', null, null);
    $this->entityManager->clear();

    $page = $this->adapter->listByIntervention(self::INTERVENTION_ID, 1, 20);

    self::assertSame(3, $page->total);
    $ids = array_map(static fn ($item): string => is_string($item->data['id']) ? $item->data['id'] : '', $page->items);
    self::assertSame([$first->data['id'], $second->data['id'], $third->data['id']], $ids);
  }

  #[Test]
  public function testListByInterventionPaginates(): void
  {
    $first = $this->adapter->append(self::INTERVENTION_ID, self::ORGANIZATION_ID, null, 'system', 'created', null, null);
    $second = $this->adapter->append(self::INTERVENTION_ID, self::ORGANIZATION_ID, self::ACTOR_ID, 'comment', 'comment', 'Note', null);
    $third = $this->adapter->append(self::INTERVENTION_ID, self::ORGANIZATION_ID, self::ACTOR_ID, 'comment', 'comment', 'Note', null);
    $this->entityManager->clear();

    $firstPage = $this->adapter->listByIntervention(self::INTERVENTION_ID, 1, 2);
    self::assertSame(3, $firstPage->total);
    self::assertCount(2, $firstPage->items);
    self::assertSame($first->data['id'], $firstPage->items[0]->data['id']);
    self::assertSame($second->data['id'], $firstPage->items[1]->data['id']);

    $secondPage = $this->adapter->listByIntervention(self::INTERVENTION_ID, 2, 2);
    self::assertSame(3, $secondPage->total);
    self::assertCount(1, $secondPage->items);
    self::assertSame($third->data['id'], $secondPage->items[0]->data['id']);
  }

  #[Test]
  public function testAppendIsIdempotentForARepeatedClientId(): void
  {
    $first = $this->adapter->append(
      self::INTERVENTION_ID,
      self::ORGANIZATION_ID,
      self::ACTOR_ID,
      'comment',
      'comment',
      'Extinguisher checked on site.',
      null,
      'outbox-client-key-1',
    );

    // The offline outbox replays a write whose response was lost in the field —
    // indistinguishable, from the device, from one that never arrived. Replaying
    // must return what was stored, not append a second comment.
    $second = $this->adapter->append(
      self::INTERVENTION_ID,
      self::ORGANIZATION_ID,
      self::ACTOR_ID,
      'comment',
      'comment',
      'Extinguisher checked on site.',
      null,
      'outbox-client-key-1',
    );

    self::assertSame($first->data['id'], $second->data['id']);
    self::assertSame(1, $this->adapter->listByIntervention(self::INTERVENTION_ID, 1, 20)->total);
  }

  #[Test]
  public function testAppendWithoutAClientIdStillAppendsEachTime(): void
  {
    $this->adapter->append(self::INTERVENTION_ID, self::ORGANIZATION_ID, self::ACTOR_ID, 'comment', 'comment', 'One', null);
    $this->adapter->append(self::INTERVENTION_ID, self::ORGANIZATION_ID, self::ACTOR_ID, 'comment', 'comment', 'Two', null);

    // Online comments carry no key and are genuinely distinct writes; the unique
    // index must not collapse them (NULLs do not collide in Postgres).
    self::assertSame(2, $this->adapter->listByIntervention(self::INTERVENTION_ID, 1, 20)->total);
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Activity Adapter Test';
    $organization->slug = 'activity-adapter-test';
    $organization->ownerUserId = '990e8400-e29b-41d4-a716-446655449000';
    $organization->createdByUserId = '990e8400-e29b-41d4-a716-446655449000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createIntervention(string $id, int $number): void
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $record = new InterventionRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->type = 'site_setup';
    $record->name = 'Intervention ' . $number;
    $record->number = $number;
    $record->status = 'draft';
    $record->priority = 'normal';
    $record->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM intervention_activities WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM interventions WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
