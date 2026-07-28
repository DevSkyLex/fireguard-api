<?php

declare(strict_types=1);

namespace Tests\Integration\Calendar\Infrastructure\DataFixtures;

use Calendar\Infrastructure\DataFixtures\CalendarFixtures;
use Calendar\Infrastructure\Persistence\Doctrine\Record\CalendarEventRecord;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Infrastructure\DataFixtures\SeedTimeline;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_filter;
use function count;

#[CoversClass(className: CalendarFixtures::class)]
final class CalendarFixturesIntegrationTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testLoadPersistsCalendarEventsStraddlingToday(): void
  {
    /** @var OrganizationFixtures $organizationFixtures */
    $organizationFixtures = static::getContainer()->get(OrganizationFixtures::class);
    /** @var FacilityFixtures $facilityFixtures */
    $facilityFixtures = static::getContainer()->get(FacilityFixtures::class);
    /** @var CalendarFixtures $calendarFixtures */
    $calendarFixtures = static::getContainer()->get(CalendarFixtures::class);

    self::assertSame(['calendar', 'main-seed'], CalendarFixtures::getGroups());
    self::assertSame([OrganizationFixtures::class, FacilityFixtures::class], $calendarFixtures->getDependencies());

    $loader = new Loader();
    $loader->addFixture($organizationFixtures);
    $loader->addFixture($facilityFixtures);
    $loader->addFixture($calendarFixtures);

    $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
    // Purge before loading: the test databases carry the seeded baseline, so
    // appending on top of it collides on primary keys and makes the counts
    // below meaningless. DAMA rolls the purge back with the rest of the test.
    $executor->execute($loader->getFixtures(), false);

    /** @var list<CalendarEventRecord> $events */
    $events = $this->entityManager->getRepository(CalendarEventRecord::class)->findBy([], ['startsAt' => 'ASC']);
    self::assertCount(10, $events);

    foreach ($events as $event) {
      self::assertSame(OrganizationFixtures::ORGANIZATION_ID, $event->organizationId);
      self::assertGreaterThan($event->startsAt, $event->endsAt);
      self::assertLessThan($event->startsAt, $event->createdAt);
      self::assertNotSame('', $event->createdByMemberId);
    }

    // The seed deliberately straddles "now" so the month grid is never empty.
    $now = SeedTimeline::now();
    $past = array_filter($events, static fn (CalendarEventRecord $event): bool => $event->startsAt < $now);
    $upcoming = array_filter($events, static fn (CalendarEventRecord $event): bool => $event->startsAt > $now);
    self::assertCount(3, $past);
    self::assertCount(7, $upcoming);

    $organizationWide = array_filter($events, static fn (CalendarEventRecord $event): bool => null === $event->facilityId);
    self::assertCount(1, $organizationWide);

    $allDay = array_filter($events, static fn (CalendarEventRecord $event): bool => $event->allDay);
    self::assertCount(2, $allDay);

    $facilityScoped = array_filter($events, static fn (CalendarEventRecord $event): bool => null !== $event->facilityId);
    self::assertSame(9, count($facilityScoped));
  }
}
