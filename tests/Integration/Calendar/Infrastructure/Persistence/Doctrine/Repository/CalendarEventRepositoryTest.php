<?php

declare(strict_types=1);

namespace Tests\Integration\Calendar\Infrastructure\Persistence\Doctrine\Repository;

use Calendar\Domain\Model\Event\CalendarEvent;
use Calendar\Domain\ValueObject\CalendarEventId;
use Calendar\Infrastructure\Persistence\Doctrine\Repository\CalendarEventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test CalendarEventRepositoryTest.
 *
 * Exercises the real DQL behind `listBetween()` (the `startsAt <= :to AND
 * COALESCE(endsAt, startsAt) >= :from` overlap query) against a live entity
 * manager — a mocked QueryBuilder would never actually parse it. No
 * `organizations` FK setup is needed: `CalendarEventRecord.organizationId`
 * is a plain string column (see `Calendar\MODULE.md`).
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CalendarEventRepository::class)]
final class CalendarEventRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '990e8400-e29b-41d4-a716-446655490001';

  private const string OTHER_ORGANIZATION_ID = '990e8400-e29b-41d4-a716-446655490002';

  private const string MEMBER_ID = '990e8400-e29b-41d4-a716-446655499000';

  private const string EVENT_ENTIRELY_BEFORE_ID = '990e8400-e29b-41d4-a716-446655490010';

  private const string EVENT_OVERLAPPING_START_ID = '990e8400-e29b-41d4-a716-446655490011';

  private const string EVENT_FULLY_INSIDE_ID = '990e8400-e29b-41d4-a716-446655490012';

  private const string EVENT_SPANNING_WHOLE_RANGE_ID = '990e8400-e29b-41d4-a716-446655490013';

  private const string EVENT_ENTIRELY_AFTER_ID = '990e8400-e29b-41d4-a716-446655490014';

  private const string EVENT_NO_END_INSIDE_ID = '990e8400-e29b-41d4-a716-446655490015';

  private const string EVENT_OTHER_ORGANIZATION_ID = '990e8400-e29b-41d4-a716-446655490016';

  private EntityManagerInterface $entityManager;

  private CalendarEventRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->repository = new CalendarEventRepository($this->entityManager);

    $this->cleanup();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveThenFindByIdRoundTrips(): void
  {
    $event = CalendarEvent::create(
      id: CalendarEventId::fromString(self::EVENT_FULLY_INSIDE_ID),
      organizationId: self::ORGANIZATION_ID,
      title: 'Fire drill',
      description: 'Quarterly fire drill',
      startsAt: new DateTimeImmutable('2026-08-10T09:00:00+00:00'),
      endsAt: new DateTimeImmutable('2026-08-10T11:00:00+00:00'),
      allDay: false,
      facilityId: null,
      createdByMemberId: self::MEMBER_ID,
    );

    $this->repository->save($event);
    $this->entityManager->clear();

    $found = $this->repository->findById(CalendarEventId::fromString(self::EVENT_FULLY_INSIDE_ID));

    self::assertNotNull($found);
    self::assertSame(self::ORGANIZATION_ID, $found->organizationId());
    self::assertSame('Fire drill', $found->title());
    self::assertSame(self::MEMBER_ID, $found->createdByMemberId());
  }

  #[Test]
  public function testRemoveDeletesTheEvent(): void
  {
    $event = CalendarEvent::create(
      id: CalendarEventId::fromString(self::EVENT_FULLY_INSIDE_ID),
      organizationId: self::ORGANIZATION_ID,
      title: 'To be deleted',
      description: null,
      startsAt: new DateTimeImmutable('2026-08-10T09:00:00+00:00'),
      endsAt: null,
      allDay: false,
      facilityId: null,
      createdByMemberId: self::MEMBER_ID,
    );
    $this->repository->save($event);

    $this->repository->remove($event);
    $this->entityManager->clear();

    self::assertNull($this->repository->findById(CalendarEventId::fromString(self::EVENT_FULLY_INSIDE_ID)));
  }

  #[Test]
  public function testListBetweenReturnsOnlyOverlappingEventsForTheRequestedOrganizationOrderedByStartsAt(): void
  {
    // Entirely before the range: excluded.
    $this->persistEvent(self::EVENT_ENTIRELY_BEFORE_ID, self::ORGANIZATION_ID, '2026-07-01T00:00:00+00:00', '2026-07-05T00:00:00+00:00');

    // Overlaps the range's start (starts before, ends inside): included.
    $this->persistEvent(self::EVENT_OVERLAPPING_START_ID, self::ORGANIZATION_ID, '2026-07-31T22:00:00+00:00', '2026-08-01T02:00:00+00:00');

    // Fully inside the range: included.
    $this->persistEvent(self::EVENT_FULLY_INSIDE_ID, self::ORGANIZATION_ID, '2026-08-10T09:00:00+00:00', '2026-08-10T11:00:00+00:00');

    // Spans the whole requested range (starts before, ends after): included.
    $this->persistEvent(self::EVENT_SPANNING_WHOLE_RANGE_ID, self::ORGANIZATION_ID, '2026-07-15T00:00:00+00:00', '2026-09-15T00:00:00+00:00');

    // Entirely after the range: excluded.
    $this->persistEvent(self::EVENT_ENTIRELY_AFTER_ID, self::ORGANIZATION_ID, '2026-09-05T00:00:00+00:00', '2026-09-06T00:00:00+00:00');

    // No end date, falls inside the range: included, ends() treated as startsAt.
    $this->persistEvent(self::EVENT_NO_END_INSIDE_ID, self::ORGANIZATION_ID, '2026-08-20T00:00:00+00:00', null);

    // Overlaps the range but belongs to a different organization: excluded.
    $this->persistEvent(self::EVENT_OTHER_ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID, '2026-08-10T00:00:00+00:00', null);

    $this->entityManager->clear();

    $events = $this->repository->listBetween(
      self::ORGANIZATION_ID,
      new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-08-31T23:59:59+00:00'),
      500,
    );

    self::assertCount(4, $events);
    // Ordered by startsAt ascending: spanning-whole-range starts 07-15,
    // overlapping-start starts 07-31 22:00, fully-inside starts 08-10,
    // no-end starts 08-20.
    self::assertSame(
      [self::EVENT_SPANNING_WHOLE_RANGE_ID, self::EVENT_OVERLAPPING_START_ID, self::EVENT_FULLY_INSIDE_ID, self::EVENT_NO_END_INSIDE_ID],
      array_map(static fn (CalendarEvent $event): string => (string) $event->id(), $events),
    );
  }

  #[Test]
  public function testListBetweenAppliesTheLimit(): void
  {
    $this->persistEvent(self::EVENT_FULLY_INSIDE_ID, self::ORGANIZATION_ID, '2026-08-10T09:00:00+00:00', null);
    $this->persistEvent(self::EVENT_NO_END_INSIDE_ID, self::ORGANIZATION_ID, '2026-08-20T00:00:00+00:00', null);
    $this->entityManager->clear();

    $events = $this->repository->listBetween(
      self::ORGANIZATION_ID,
      new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-08-31T23:59:59+00:00'),
      1,
    );

    self::assertCount(1, $events);
  }

  private function persistEvent(string $id, string $organizationId, string $startsAt, ?string $endsAt): void
  {
    $event = CalendarEvent::create(
      id: CalendarEventId::fromString($id),
      organizationId: $organizationId,
      title: 'Event ' . $id,
      description: null,
      startsAt: new DateTimeImmutable($startsAt),
      endsAt: null !== $endsAt ? new DateTimeImmutable($endsAt) : null,
      allDay: false,
      facilityId: null,
      createdByMemberId: self::MEMBER_ID,
    );
    $this->repository->save($event);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM calendar_events WHERE organization_id IN (:organizationId, :otherOrganizationId)',
      ['organizationId' => self::ORGANIZATION_ID, 'otherOrganizationId' => self::OTHER_ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
