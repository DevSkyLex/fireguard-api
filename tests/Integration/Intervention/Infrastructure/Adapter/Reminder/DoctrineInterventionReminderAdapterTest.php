<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Reminder;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Adapter\Reminder\DoctrineInterventionReminderAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test DoctrineInterventionReminderAdapter.
 *
 * Exercises the real DQL date-window and status-set filtering that is hard
 * to trust from a mock, plus the anti-spam stamps.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrineInterventionReminderAdapter::class)]
final class DoctrineInterventionReminderAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655450001';

  private const string OWNER_USER_ID = '660e8400-e29b-41d4-a716-446655459000';

  private EntityManagerInterface $entityManager;

  private DoctrineInterventionReminderAdapter $adapter;

  private int $number = 1;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var DoctrineInterventionReminderAdapter $adapter */
    $adapter = static::getContainer()->get(DoctrineInterventionReminderAdapter::class);
    $this->adapter = $adapter;

    $this->createOrganization();
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
  public function testPageDueSoonSelectsOnlyActiveStatusesWithinTheWindowAndUnstamped(): void
  {
    $now = new DateTimeImmutable('2026-06-15 12:00:00');
    $threshold = $now->modify('+48 hours');

    $inWindow = $this->persistIntervention('int-due', 'planned', $now->modify('+10 hours'));
    $outsideWindow = $this->persistIntervention('int-far', 'planned', $now->modify('+72 hours'));
    $wrongStatus = $this->persistIntervention('int-draft', 'draft', $now->modify('+10 hours'));
    $alreadyStamped = $this->persistIntervention('int-stamped', 'in_progress', $now->modify('+10 hours'));
    $this->stampDueSoon($alreadyStamped, $now->modify('-1 hour'));

    $page = $this->adapter->pageDueSoon($now, $threshold, 50, 0);

    $ids = array_map(static fn ($item): string => $item->id, $page->items);
    self::assertContains($inWindow, $ids);
    self::assertNotContains($outsideWindow, $ids);
    self::assertNotContains($wrongStatus, $ids);
    self::assertNotContains($alreadyStamped, $ids);
  }

  #[Test]
  public function testPageOverdueSelectsOnlyPastDueUnstampedActiveStatuses(): void
  {
    $now = new DateTimeImmutable('2026-06-15 12:00:00');

    $past = $this->persistIntervention('int-overdue', 'changes_requested', $now->modify('-1 hour'));
    $future = $this->persistIntervention('int-future', 'changes_requested', $now->modify('+1 hour'));
    $terminal = $this->persistIntervention('int-published', 'published', $now->modify('-1 hour'));

    $page = $this->adapter->pageOverdue($now, 50, 0);

    $ids = array_map(static fn ($item): string => $item->id, $page->items);
    self::assertContains($past, $ids);
    self::assertNotContains($future, $ids);
    self::assertNotContains($terminal, $ids);
  }

  #[Test]
  public function testMarkDueSoonNotifiedStampsTheRecordAndRemovesItFromSubsequentPages(): void
  {
    $now = new DateTimeImmutable('2026-06-15 12:00:00');
    $threshold = $now->modify('+48 hours');
    $id = $this->persistIntervention('int-mark', 'planned', $now->modify('+5 hours'));

    $before = $this->adapter->pageDueSoon($now, $threshold, 50, 0);
    self::assertContains($id, array_map(static fn ($item): string => $item->id, $before->items));

    $this->adapter->markDueSoonNotified($id, $now);
    $this->entityManager->clear();

    $after = $this->adapter->pageDueSoon($now, $threshold, 50, 0);
    self::assertNotContains($id, array_map(static fn ($item): string => $item->id, $after->items));

    $stamp = $this->entityManager->getConnection()->fetchOne(
      'SELECT due_soon_notified_at FROM interventions WHERE id = :id',
      ['id' => $id],
    );
    self::assertNotNull($stamp);
  }

  #[Test]
  public function testMarkOverdueNotifiedStampsTheRecord(): void
  {
    $now = new DateTimeImmutable('2026-06-15 12:00:00');
    $id = $this->persistIntervention('int-mark-overdue', 'planned', $now->modify('-1 hour'));

    $this->adapter->markOverdueNotified($id, $now);
    $this->entityManager->clear();

    $after = $this->adapter->pageOverdue($now, 50, 0);
    self::assertNotContains($id, array_map(static fn ($item): string => $item->id, $after->items));
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Reminder Adapter Test';
    $organization->slug = 'reminder-adapter-test';
    $organization->ownerUserId = self::OWNER_USER_ID;
    $organization->createdByUserId = self::OWNER_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function persistIntervention(string $id, string $status, DateTimeImmutable $dueAt): string
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $record = new InterventionRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->type = 'site_setup';
    $record->name = 'Intervention ' . $id;
    $record->number = $this->number++;
    $record->status = $status;
    $record->responsibleId = null;
    $record->participants = [];
    $record->plannedStartAt = $dueAt->modify('-1 day');
    $record->dueAt = $dueAt;
    $record->createdAt = new DateTimeImmutable('2026-01-01 00:00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    return $id;
  }

  private function stampDueSoon(string $id, DateTimeImmutable $at): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'UPDATE interventions SET due_soon_notified_at = :at WHERE id = :id',
      ['at' => $at, 'id' => $id],
      ['at' => 'datetime_immutable'],
    );
    $this->entityManager->clear();
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
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
