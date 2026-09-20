<?php

declare(strict_types=1);

namespace Workload\Infrastructure\DataFixtures;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use RuntimeException;
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Infrastructure\DataFixtures\SeedUuid;
use Workload\Application\Port\Inbound\WorkloadCoordinationPort;
use Workload\Infrastructure\Persistence\Doctrine\Record\{CapacityExceptionRecord, CapacityWeekRecord};

/**
 * Opt-in, append-safe capacity examples for the existing seed organization.
 *
 * Seven-hour weekdays are demo data, never a product default. Existing schedules
 * and overlapping exceptions win over these examples, including cancelled ones.
 *
 * @category Fixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkloadFixtures implements FixtureInterface, FixtureGroupInterface
{
  private const string ORGANIZATION = '11111111-1111-4111-8111-111111111111';

  private const string OWNER = '11111111-1111-4111-8111-111111111115';

  private const string FIELD_TECHNICIAN = '0034f559-02ce-4ddb-a8b7-2e47dbc0be2c';

  private const string PARIS_TECHNICIAN = '126b5cfc-208e-48b0-ae88-7bd97a1eecf8';

  private const string COORDINATOR = '853db03d-8a54-4c57-9faa-822de84c93f4';

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param OrganizationWorkforceDirectoryPort $workforce public organization context
   * @param ClockPort $clock anchors examples to the current local week
   * @param WorkloadCoordinationPort $coordination serializes writes with capacity edits
   */
  public function __construct(
    private OrganizationWorkforceDirectoryPort $workforce,
    private ClockPort $clock,
    private WorkloadCoordinationPort $coordination,
  ) {
  }

  /**
   * @since 1.0.0
   *
   * @return list<string> opt-in groups, excluded from the standard seed baseline
   */
  public static function getGroups(): array
  {
    return ['workload'];
  }

  /**
   * @since 1.0.0
   *
   * @param ObjectManager $manager main object manager inside the caller's transaction
   *
   * @return void adds missing examples without updating existing records
   */
  public function load(ObjectManager $manager): void
  {
    $context = $this->workforce->context(self::ORGANIZATION);
    if (null === $context) {
      throw new RuntimeException('The seed organization is missing. Workload fixtures require the existing development seed baseline.');
    }
    $members = [];
    foreach ($this->workforce->members(self::ORGANIZATION) as $member) {
      if ($member->active) {
        $members[$member->id] = true;
      }
    }
    foreach ([self::OWNER, self::FIELD_TECHNICIAN, self::PARIS_TECHNICIAN, self::COORDINATOR] as $memberId) {
      if (!isset($members[$memberId])) {
        throw new RuntimeException('A required workload demo member is missing or inactive: ' . $memberId);
      }
    }

    $this->coordination->acquire(self::ORGANIZATION, [], true);
    $now = $this->clock->now();
    $monday = $now->setTimezone(new DateTimeZone($context->timezone))->modify('monday this week')->setTime(0, 0);
    $this->week($manager, self::ORGANIZATION, [420, 420, 420, 420, 420, 0, 0], $monday, $now);
    $this->week($manager, self::FIELD_TECHNICIAN, [240, 240, 240, 240, 240, 0, 0], $monday, $now);
    $manager->flush();

    foreach ([0, 7] as $offset) {
      $this->exception($manager, self::PARIS_TECHNICIAN, $monday->modify('+' . ($offset + 3) . ' days'), 0, $now);
      $this->exception($manager, self::COORDINATOR, $monday->modify('+' . ($offset + 4) . ' days'), 210, $now);
    }
    $manager->flush();
  }

  /**
   * @since 1.0.0
   *
   * @param ObjectManager $manager main object manager
   * @param string $scope organization or member identifier
   * @param list<int> $minutes Monday through Sunday demo capacity
   * @param DateTimeImmutable $effectiveOn first local date of the demo week
   * @param DateTimeImmutable $now audit timestamp
   *
   * @return void preserves any existing schedule, including future changes
   */
  private function week(ObjectManager $manager, string $scope, array $minutes, DateTimeImmutable $effectiveOn, DateTimeImmutable $now): void
  {
    if (null !== $manager->getRepository(CapacityWeekRecord::class)->findOneBy(['organizationId' => self::ORGANIZATION, 'scopeId' => $scope])) {
      return;
    }
    $week = new CapacityWeekRecord();
    $week->id = SeedUuid::from('workload-demo:capacity:' . $scope);
    $week->organizationId = self::ORGANIZATION;
    $week->scopeId = $scope;
    $week->effectiveOn = $effectiveOn->format('Y-m-d');
    $week->minutes = $minutes;
    $week->createdBy = self::OWNER;
    $week->createdAt = $now;
    $manager->persist($week);
  }

  /**
   * @since 1.0.0
   *
   * @param ObjectManager $manager main object manager
   * @param string $memberId member whose availability is reduced
   * @param DateTimeImmutable $date local date
   * @param int $minutes actual available minutes
   * @param DateTimeImmutable $now audit timestamp
   *
   * @return void skips dates already covered by user-managed exceptions
   */
  private function exception(ObjectManager $manager, string $memberId, DateTimeImmutable $date, int $minutes, DateTimeImmutable $now): void
  {
    $day = $date->format('Y-m-d');
    $id = SeedUuid::from('workload-demo:exception:' . $memberId . ':' . $day);
    if (null !== $manager->find(CapacityExceptionRecord::class, $id)) {
      return;
    }
    $capacity = null;
    foreach ([$memberId, self::ORGANIZATION] as $scope) {
      foreach ($manager->getRepository(CapacityWeekRecord::class)->findBy(['organizationId' => self::ORGANIZATION, 'scopeId' => $scope], ['effectiveOn' => 'DESC']) as $week) {
        if ($week->effectiveOn <= $day) {
          $capacity = $week->minutes[(int) $date->format('N') - 1];

          break 2;
        }
      }
    }
    // An exception must not invent capacity or exceed a user-configured week.
    if (null === $capacity || $minutes > $capacity) {
      return;
    }
    foreach ($manager->getRepository(CapacityExceptionRecord::class)->findBy(['organizationId' => self::ORGANIZATION, 'memberId' => $memberId, 'cancelledAt' => null]) as $existing) {
      if ($existing->startsOn <= $day && $existing->endsOn >= $day) {
        return;
      }
    }
    $exception = new CapacityExceptionRecord();
    $exception->id = $id;
    $exception->organizationId = self::ORGANIZATION;
    $exception->memberId = $memberId;
    $exception->startsOn = $day;
    $exception->endsOn = $day;
    $exception->minutes = $minutes;
    $exception->createdBy = self::OWNER;
    $exception->createdAt = $now;
    $manager->persist($exception);
  }
  // #endregion
}
