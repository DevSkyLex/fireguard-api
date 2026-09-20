<?php

declare(strict_types=1);

namespace Tests\Integration\Workload\Infrastructure\DataFixtures;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Port\Inbound\InterventionWorkloadContributionsPort;
use Intervention\Infrastructure\DataFixtures\InterventionWorkloadFixtures;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionTimeEntryRecord, InterventionTimeEntryVersionRecord, InterventionWorkItemAssignmentRecord, InterventionWorkItemRecord};
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Infrastructure\Console\AppendSeedFixturesCommand;
use Shared\Infrastructure\DataFixtures\SeedUuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Workload\Application\Contract\Projection\{MemberWorkloadView, WorkloadDayView, WorkloadProjectionView};
use Workload\Application\Port\Inbound\WorkloadCoordinationPort;
use Workload\Application\Port\Outbound\CapacityRepositoryPort;
use Workload\Application\Service\WorkloadProjector;
use Workload\Infrastructure\DataFixtures\WorkloadFixtures;
use Workload\Infrastructure\Persistence\Doctrine\Record\{CapacityExceptionRecord, CapacityWeekRecord};

use function array_column;
use function array_filter;
use function array_map;
use function array_slice;
use function array_sum;
use function array_values;
use function count;

#[CoversClass(WorkloadFixtures::class)]
#[CoversClass(InterventionWorkloadFixtures::class)]
#[CoversClass(AppendSeedFixturesCommand::class)]
final class WorkloadFixturesIntegrationTest extends KernelTestCase
{
  private const string ORGANIZATION = '11111111-1111-4111-8111-111111111111';

  private const string OWNER = '11111111-1111-4111-8111-111111111115';

  private const string PARIS_TECHNICIAN = '126b5cfc-208e-48b0-ae88-7bd97a1eecf8';

  private const string COORDINATOR = '853db03d-8a54-4c57-9faa-822de84c93f4';

  private EntityManagerInterface $manager;

  private ClockPort $clock;

  private OrganizationWorkforceDirectoryPort $workforce;

  protected function setUp(): void
  {
    self::bootKernel();
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    $this->manager = $manager;
    $workforce = self::getContainer()->get(OrganizationWorkforceDirectoryPort::class);
    self::assertInstanceOf(OrganizationWorkforceDirectoryPort::class, $workforce);
    $this->workforce = $workforce;
    $clock = $this->createStub(ClockPort::class);
    $context = $this->workforce->context(self::ORGANIZATION);
    self::assertNotNull($context);
    // A local instant near midnight catches accidental UTC day anchoring.
    $now = new DateTimeImmutable('2026-09-16 01:30:00', new DateTimeZone($context->timezone));
    $clock->method('now')->willReturn($now->setTimezone(new DateTimeZone('UTC')));
    $this->clock = $clock;
  }

  #[Test]
  public function testExamplesProduceActualsDraftsDailyOverloadAndExplicitUnknownWork(): void
  {
    $before = $this->counts();
    $this->append();
    $after = $this->counts();
    self::assertSame([5, 67, 18, 19, 65, 2, 4], array_map(static fn (int $count, int $previous): int => $count - $previous, $after, $before));

    $projection = $this->projection();
    self::assertSame('2026-09-16', $projection->today);
    self::assertSame('partial', $projection->completeness);
    $owner = $this->member($projection, self::OWNER);
    $wednesday = $this->day($owner, '2026-09-16');
    self::assertSame(420, $wednesday->capacityMinutes);
    self::assertSame(120, $wednesday->actualMinutes);
    self::assertSame(360, $wednesday->remainingMinutes);
    self::assertSame(60, $wednesday->overloadMinutes);
    self::assertGreaterThan(0, $wednesday->draftMinutes);
    $weeklyDemand = array_sum(array_map(static fn (WorkloadDayView $day): int => $day->actualMinutes + $day->remainingMinutes, array_slice($owner->days, 0, 7)));
    self::assertLessThan(2100, $weeklyDemand, 'The daily overload must remain visible despite a weekly total below capacity.');
    self::assertSame(240, $this->day($this->member($projection, '0034f559-02ce-4ddb-a8b7-2e47dbc0be2c'), '2026-09-16')->capacityMinutes);
    $paris = $this->member($projection, self::PARIS_TECHNICIAN);
    self::assertSame(0, $this->day($paris, '2026-09-17')->capacityMinutes);
    self::assertContains('no_available_day', array_column($paris->unallocated, 'reason'));
    self::assertContains('overdue', array_column($paris->unallocated, 'reason'));
    $coordinator = $this->member($projection, self::COORDINATOR);
    self::assertSame(210, $this->day($coordinator, '2026-09-18')->capacityMinutes);
    self::assertContains('unestimated', array_column($coordinator->unallocated, 'reason'));
    self::assertContains('undated', array_column($coordinator->unallocated, 'reason'));
    self::assertGreaterThanOrEqual(2, count($projection->unassigned));

    $item = $this->todayTask();
    self::assertSame(360, $item->estimatedMinutes);
    self::assertSame(360, $item->remainingMinutes, 'Time entries never deduct remaining work.');
    $entry = $this->manager->getRepository(InterventionTimeEntryRecord::class)->findOneBy(['workItem' => $item->id]);
    self::assertNotNull($entry);
    self::assertSame(2, $entry->revision);
    self::assertSame(90, $this->manager->find(InterventionTimeEntryVersionRecord::class, ['entry' => $entry->id, 'revision' => 1])?->minutes);
    self::assertSame(120, $this->manager->find(InterventionTimeEntryVersionRecord::class, ['entry' => $entry->id, 'revision' => 2])?->minutes);
    foreach ($this->manager->getRepository(InterventionTimeEntryRecord::class)->findAll() as $time) {
      self::assertLessThanOrEqual('2026-09-16', $time->workedOn);
    }
  }

  #[Test]
  public function testReplayPreservesUserEditsAndDoesNotConsumeNewInterventionNumbers(): void
  {
    $this->append();
    $item = $this->todayTask();
    $item->remainingMinutes = 75;
    self::assertNotNull($item->intervention);
    $item->intervention->name = 'Edited by the planner';
    $week = $this->manager->getRepository(CapacityWeekRecord::class)->findOneBy(['scopeId' => self::ORGANIZATION]);
    self::assertNotNull($week);
    $week->minutes = [300, 300, 300, 300, 300, 0, 0];
    $exception = $this->manager->getRepository(CapacityExceptionRecord::class)->findOneBy(['memberId' => self::PARIS_TECHNICIAN]);
    self::assertNotNull($exception);
    $exceptionId = $exception->id;
    $exception->cancelledAt = $this->clock->now();
    $exception->cancelledBy = self::OWNER;
    $this->manager->flush();
    $counts = $this->counts();
    $number = $this->manager->getConnection()->fetchOne('SELECT last_number FROM intervention_number_counters WHERE organization_id = ?', [self::ORGANIZATION]);

    $this->append();

    self::assertSame($counts, $this->counts());
    self::assertSame($number, $this->manager->getConnection()->fetchOne('SELECT last_number FROM intervention_number_counters WHERE organization_id = ?', [self::ORGANIZATION]));
    self::assertSame(75, $this->todayTask()->remainingMinutes);
    self::assertSame('Edited by the planner', $this->todayTask()->intervention?->name);
    self::assertSame([300, 300, 300, 300, 300, 0, 0], $this->manager->find(CapacityWeekRecord::class, $week->id)?->minutes);
    self::assertNotNull($this->manager->find(CapacityExceptionRecord::class, $exceptionId)?->cancelledAt);
  }

  #[Test]
  public function testExistingCapacityAndOverlappingAbsencesAreNeverOverwritten(): void
  {
    $week = new CapacityWeekRecord();
    $week->id = SeedUuid::from('user-managed-week');
    $week->organizationId = self::ORGANIZATION;
    $week->scopeId = self::ORGANIZATION;
    $week->effectiveOn = '2026-01-01';
    $week->minutes = [120, 120, 120, 120, 120, 0, 0];
    $week->createdBy = self::OWNER;
    $week->createdAt = $this->clock->now();
    $absence = new CapacityExceptionRecord();
    $absence->id = SeedUuid::from('user-managed-absence');
    $absence->organizationId = self::ORGANIZATION;
    $absence->memberId = self::PARIS_TECHNICIAN;
    $absence->startsOn = '2026-09-14';
    $absence->endsOn = '2026-09-30';
    $absence->minutes = 0;
    $absence->createdBy = self::OWNER;
    $absence->createdAt = $this->clock->now();
    $this->manager->persist($week);
    $this->manager->persist($absence);
    $this->manager->flush();

    $this->append();

    self::assertSame(1, $this->manager->getRepository(CapacityWeekRecord::class)->count(['scopeId' => self::ORGANIZATION]));
    self::assertSame(1, $this->manager->getRepository(CapacityExceptionRecord::class)->count(['memberId' => self::PARIS_TECHNICIAN]));
    self::assertSame(120, $this->day($this->member($this->projection(), self::OWNER), '2026-09-16')->capacityMinutes);
    self::assertSame(0, $this->manager->getRepository(CapacityExceptionRecord::class)->count(['memberId' => self::COORDINATOR]), 'The 210-minute example cannot exceed an existing 120-minute capacity.');
  }

  #[Test]
  public function testMissingInterventionMemberRollsBackCapacityFixturesToo(): void
  {
    $before = $this->counts();
    $directory = $this->createStub(OrganizationWorkforceDirectoryPort::class);
    $directory->method('context')->willReturn($this->workforce->context(self::ORGANIZATION));
    $directory->method('members')->willReturn(array_values(array_filter($this->workforce->members(self::ORGANIZATION), static fn ($member): bool => 'd0c2c74e-0c66-438c-b7e1-2b10f54bbd79' !== $member->id)));
    $tester = $this->command($directory);

    self::assertSame(Command::FAILURE, $tester->execute(['group' => 'workload']));
    self::assertStringContainsString('missing or inactive', $tester->getDisplay());
    self::assertSame($before, $this->counts());
  }

  private function append(): void
  {
    $tester = $this->command($this->workforce);
    self::assertSame(Command::SUCCESS, $tester->execute(['group' => 'workload']), $tester->getDisplay());
  }

  private function command(OrganizationWorkforceDirectoryPort $workforce): CommandTester
  {
    $coordination = self::getContainer()->get(WorkloadCoordinationPort::class);
    self::assertInstanceOf(WorkloadCoordinationPort::class, $coordination);

    return new CommandTester(new AppendSeedFixturesCommand($this->manager, 'test', [
      new WorkloadFixtures($workforce, $this->clock, $coordination),
      new InterventionWorkloadFixtures($workforce, $this->clock, $coordination),
    ]));
  }

  private function projection(): WorkloadProjectionView
  {
    $contributions = self::getContainer()->get(InterventionWorkloadContributionsPort::class);
    self::assertInstanceOf(InterventionWorkloadContributionsPort::class, $contributions);
    $capacities = self::getContainer()->get(CapacityRepositoryPort::class);
    self::assertInstanceOf(CapacityRepositoryPort::class, $capacities);
    $projector = new WorkloadProjector($contributions, $this->workforce, $capacities, $this->clock);

    return $projector->project(self::ORGANIZATION, '2026-09-14', '2026-09-27')->view;
  }

  private function member(WorkloadProjectionView $projection, string $id): MemberWorkloadView
  {
    foreach ($projection->members as $member) {
      if ($member->memberId === $id) {
        return $member;
      }
    }
    self::fail('Missing demo member: ' . $id);
  }

  private function day(MemberWorkloadView $member, string $date): WorkloadDayView
  {
    foreach ($member->days as $day) {
      if ($day->date === $date) {
        return $day;
      }
    }
    self::fail('Missing workload day: ' . $date);
  }

  private function todayTask(): InterventionWorkItemRecord
  {
    $interventionId = SeedUuid::from('workload-demo:intervention:operations:2026-09-14');
    $item = $this->manager->find(InterventionWorkItemRecord::class, SeedUuid::from('workload-demo:task:' . $interventionId . ':' . self::OWNER . ':2026-09-16'));
    self::assertNotNull($item);

    return $item;
  }

  /**
   * @return list<int>
   */
  private function counts(): array
  {
    return array_map(fn (string $class): int => $this->manager->getRepository($class)->count([]), [
      InterventionRecord::class, InterventionWorkItemRecord::class, InterventionTimeEntryRecord::class,
      InterventionTimeEntryVersionRecord::class, InterventionWorkItemAssignmentRecord::class,
      CapacityWeekRecord::class, CapacityExceptionRecord::class,
    ]);
  }
}
