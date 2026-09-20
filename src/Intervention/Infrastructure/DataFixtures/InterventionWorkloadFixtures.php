<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\DataFixtures;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionActivityRecord, InterventionRecord, InterventionTimeEntryRecord, InterventionTimeEntryVersionRecord, InterventionWorkItemAssignmentRecord, InterventionWorkItemRecord};
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use RuntimeException;
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Infrastructure\DataFixtures\SeedUuid;
use Workload\Application\Port\Inbound\WorkloadCoordinationPort;

use function array_keys;
use function is_numeric;

/**
 * Opt-in workload examples owned by Intervention, independent of capacity storage.
 *
 * Weekly stable identifiers make append replay safe. Existing interventions and
 * their entire task/journal trees are left untouched, even after user edits.
 *
 * @category Fixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionWorkloadFixtures implements FixtureInterface, FixtureGroupInterface
{
  private const string ORGANIZATION = '11111111-1111-4111-8111-111111111111';

  private const string OWNER = '11111111-1111-4111-8111-111111111115';

  private const string FIELD_TECHNICIAN = '0034f559-02ce-4ddb-a8b7-2e47dbc0be2c';

  private const string PARIS_TECHNICIAN = '126b5cfc-208e-48b0-ae88-7bd97a1eecf8';

  private const string COORDINATOR = '853db03d-8a54-4c57-9faa-822de84c93f4';

  /**
   * @var array<string, string> existing seed members and human-readable task labels
   */
  private const array TASKS = [
    self::OWNER => 'Review the site inventory',
    'd0c2c74e-0c66-438c-b7e1-2b10f54bbd79' => 'Check fire safety documentation',
    self::PARIS_TECHNICIAN => 'Inventory extinguishers on site',
    self::FIELD_TECHNICIAN => 'Check emergency signage',
    self::COORDINATOR => 'Reconcile regional equipment records',
    '24fcc785-bd58-43b9-9028-47f01b66d9fe' => 'Inventory warehouse safety equipment',
  ];

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param OrganizationWorkforceDirectoryPort $workforce public regional and membership context
   * @param ClockPort $clock anchors demo tasks to the current local week
   * @param WorkloadCoordinationPort $coordination coordinates with live planning mutations
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
   * @return void appends examples without altering existing operational data
   */
  public function load(ObjectManager $manager): void
  {
    if (!$manager instanceof EntityManagerInterface) {
      throw new RuntimeException('Workload fixtures require the main ORM entity manager.');
    }
    $context = $this->workforce->context(self::ORGANIZATION);
    $source = $manager->getRepository(InterventionRecord::class)->findOneBy(['organization' => self::ORGANIZATION], ['number' => 'ASC']);
    if (null === $context || null === $source || null === $source->organization || null === $source->siteId) {
      throw new RuntimeException('Workload fixtures require the existing seed organization and an intervention with a site. No baseline is auto-loaded.');
    }
    $active = [];
    foreach ($this->workforce->members(self::ORGANIZATION) as $member) {
      if ($member->active) {
        $active[$member->id] = true;
      }
    }
    foreach (array_keys(self::TASKS) as $memberId) {
      if (!isset($active[$memberId])) {
        throw new RuntimeException('A required workload demo member is missing or inactive: ' . $memberId);
      }
    }

    $this->coordination->acquire(self::ORGANIZATION, [], true);
    $now = $this->clock->now();
    $today = $now->setTimezone(new DateTimeZone($context->timezone))->setTime(0, 0);
    $monday = $today->modify('monday this week');
    foreach ([0, 7] as $offset) {
      $start = $monday->modify('+' . $offset . ' days');
      $intervention = $this->intervention($manager, $source, 'operations:' . $start->format('Y-m-d'), 'Workload demo - field operations - ' . $start->format('Y-m-d'), 0 === $offset ? 'in_progress' : 'planned', $start, $start->modify('+6 days'), $now);
      if (null === $intervention) {
        continue;
      }
      for ($day = 0; $day < 5; ++$day) {
        $date = $start->modify('+' . $day . ' days');
        $isToday = $date->format('Y-m-d') === $today->format('Y-m-d');
        $index = 0;
        foreach (self::TASKS as $memberId => $label) {
          $remaining = 120 + (($day + $index++) % 4) * 60;
          if (self::OWNER === $memberId) {
            $remaining = $isToday ? 360 : (0 === $day ? 480 : 240);
          } elseif (self::FIELD_TECHNICIAN === $memberId && 2 === $day) {
            $remaining = 300;
          }
          $item = $this->task($manager, $intervention, $memberId . ':' . $date->format('Y-m-d'), $label, $memberId, $remaining, $date, $date, $now);
          if ($date < $today) {
            $item->status = 'completed';
            $item->remainingMinutes = 0;
            $this->time($manager, $item, $memberId, $date, $remaining - 30, $now);
          } elseif ($isToday) {
            $item->status = 'in_progress';
            $this->time($manager, $item, $memberId, $date, 120, $now, self::OWNER === $memberId);
          }
        }
      }
      $this->task($manager, $intervention, 'unestimated', 'Additional equipment survey - estimate needed', self::COORDINATOR, null, $start, $start->modify('+4 days'), $now);
      $this->task($manager, $intervention, 'unassigned', 'Loading bay inventory - assignment needed', null, 180, $start, $start->modify('+4 days'), $now);
    }

    $draft = $this->intervention($manager, $source, 'forecast:' . $monday->format('Y-m-d'), 'Workload demo - upcoming campaign (draft)', 'draft', $monday, $monday->modify('+13 days'), $now);
    if (null !== $draft) {
      $this->task($manager, $draft, 'forecast', 'Prepare the next inventory campaign', self::OWNER, 300, $monday, $monday->modify('+11 days'), $now);
    }
    $undated = $this->intervention($manager, $source, 'undated:' . $monday->format('Y-m-d'), 'Workload demo - dates to confirm', 'draft', null, null, $now);
    if (null !== $undated) {
      $this->task($manager, $undated, 'undated', 'Reschedule the equipment survey', self::COORDINATOR, 120, null, null, $now);
    }
    $overdue = $this->intervention($manager, $source, 'overdue:' . $monday->format('Y-m-d'), 'Workload demo - overdue follow-up', 'in_progress', $monday->modify('-7 days'), $monday->modify('-3 days'), $now);
    if (null !== $overdue) {
      $this->task($manager, $overdue, 'overdue', 'Finish the previous inventory report', self::PARIS_TECHNICIAN, 90, null, null, $now);
    }
    $manager->flush();
  }

  /**
   * @since 1.0.0
   *
   * @param EntityManagerInterface $manager main entity manager
   * @param InterventionRecord $source existing same-module record providing organization and site associations
   * @param string $key stable scenario identifier
   * @param string $name demo title
   * @param string $status legal seeded workflow state
   * @param ?DateTimeImmutable $start local first working date
   * @param ?DateTimeImmutable $end local last working date
   * @param DateTimeImmutable $now audit timestamp
   *
   * @return ?InterventionRecord null when the scenario already exists and must be preserved
   */
  private function intervention(EntityManagerInterface $manager, InterventionRecord $source, string $key, string $name, string $status, ?DateTimeImmutable $start, ?DateTimeImmutable $end, DateTimeImmutable $now): ?InterventionRecord
  {
    $id = SeedUuid::from('workload-demo:intervention:' . $key);
    if (null !== $manager->find(InterventionRecord::class, $id)) {
      return null;
    }
    // The same atomic allocator as live creation; never reset an existing counter.
    $number = $manager->getConnection()->fetchOne(
      'INSERT INTO intervention_number_counters (organization_id, last_number) VALUES (:organization, 1) '
      . 'ON CONFLICT (organization_id) DO UPDATE SET last_number = intervention_number_counters.last_number + 1 RETURNING last_number',
      ['organization' => self::ORGANIZATION],
    );
    if (!is_numeric($number)) {
      throw new RuntimeException('Could not allocate a workload demo intervention number.');
    }
    $intervention = new InterventionRecord();
    $intervention->id = $id;
    $intervention->organization = $source->organization;
    $intervention->number = (int) $number;
    $intervention->name = $name;
    $intervention->type = 'inventory';
    $intervention->status = $status;
    $intervention->description = 'Demo data for workload planning: recorded time and remaining effort are independent. Safe to edit; fixture replay preserves this intervention.';
    $intervention->siteId = $source->siteId;
    $intervention->responsibleId = self::OWNER;
    $intervention->participants = array_keys(self::TASKS);
    $intervention->plannedStartAt = $start?->setTime(8, 0);
    $intervention->dueAt = $end?->setTime(18, 0);
    $intervention->createdAt = $now->modify('-21 days');
    $intervention->updatedAt = $now;
    $manager->persist($intervention);

    $path = match ($status) {
      'in_progress' => ['draft', 'planned', 'in_progress'],
      'planned' => ['draft', 'planned'],
      default => ['draft'],
    };
    $previous = 'draft';
    foreach ($path as $index => $state) {
      $activity = new InterventionActivityRecord();
      $activity->id = SeedUuid::from('workload-demo:activity:' . $id . ':' . $state);
      $activity->intervention = $intervention;
      $activity->organizationId = self::ORGANIZATION;
      $activity->actorId = self::OWNER;
      $activity->event = 0 === $index ? 'created' : 'status_changed';
      $activity->payload = 0 === $index ? null : ['from' => $previous, 'to' => $state];
      $activity->createdAt = $intervention->createdAt->modify('+' . $index . ' hours');
      $manager->persist($activity);
      $previous = $state;
    }

    return $intervention;
  }

  /**
   * @since 1.0.0
   *
   * @param ObjectManager $manager main object manager
   * @param InterventionRecord $intervention new demo intervention
   * @param string $key scenario-local stable key
   * @param string $label human-readable work target
   * @param ?string $memberId current assignee, null for the unassigned example
   * @param ?int $minutes explicit estimate and initial remaining effort
   * @param ?DateTimeImmutable $start optional local task start
   * @param ?DateTimeImmutable $end optional local task end
   * @param DateTimeImmutable $now audit timestamp
   *
   * @return InterventionWorkItemRecord newly persisted task
   */
  private function task(ObjectManager $manager, InterventionRecord $intervention, string $key, string $label, ?string $memberId, ?int $minutes, ?DateTimeImmutable $start, ?DateTimeImmutable $end, DateTimeImmutable $now): InterventionWorkItemRecord
  {
    $item = new InterventionWorkItemRecord();
    $item->id = SeedUuid::from('workload-demo:task:' . $intervention->id . ':' . $key);
    $item->intervention = $intervention;
    $item->action = 'inventory';
    $item->target = $label;
    $item->assigneeId = $memberId;
    $item->estimatedMinutes = $minutes;
    $item->remainingMinutes = $minutes;
    $item->workStartsOn = $start?->format('Y-m-d');
    $item->workEndsOn = $end?->format('Y-m-d');
    $item->createdAt = $intervention->createdAt;
    $item->updatedAt = $now;
    $manager->persist($item);
    if (null !== $memberId) {
      $assignment = new InterventionWorkItemAssignmentRecord();
      $assignment->id = SeedUuid::from('workload-demo:assignment:' . $item->id);
      $assignment->workItem = $item;
      $assignment->memberId = $memberId;
      $assignment->actorId = self::OWNER;
      $assignment->assignedAt = $item->createdAt;
      $manager->persist($assignment);
    }

    return $item;
  }

  /**
   * @since 1.0.0
   *
   * @param ObjectManager $manager main object manager
   * @param InterventionWorkItemRecord $item new demo task
   * @param string $memberId contributor and author
   * @param DateTimeImmutable $date past or current worked date, never future
   * @param int $minutes actual time, independent of remaining effort
   * @param DateTimeImmutable $now audit timestamp
   * @param bool $corrected whether to retain an example correction in the journal
   *
   * @return void appends a journal entry and every audited version
   */
  private function time(ObjectManager $manager, InterventionWorkItemRecord $item, string $memberId, DateTimeImmutable $date, int $minutes, DateTimeImmutable $now, bool $corrected = false): void
  {
    $entry = new InterventionTimeEntryRecord();
    $entry->id = SeedUuid::from('workload-demo:time:' . $item->id);
    $entry->workItem = $item;
    $entry->organizationId = self::ORGANIZATION;
    $entry->memberId = $memberId;
    $entry->workedOn = $date->format('Y-m-d');
    $entry->minutes = $minutes;
    $entry->note = 'Site inventory and record verification (demo).';
    $entry->revision = $corrected ? 2 : 1;
    $entry->createdBy = $memberId;
    $entry->updatedBy = $memberId;
    $entry->createdAt = $now->modify('-1 minute');
    $entry->updatedAt = $now;
    $manager->persist($entry);
    for ($revision = 1; $revision <= $entry->revision; ++$revision) {
      $version = new InterventionTimeEntryVersionRecord();
      $version->entry = $entry;
      $version->revision = $revision;
      $version->workedOn = $entry->workedOn;
      $version->minutes = $corrected && 1 === $revision ? $minutes - 30 : $minutes;
      $version->note = $entry->note;
      $version->actorId = $memberId;
      $version->recordedAt = 1 === $revision ? $entry->createdAt : $now;
      $manager->persist($version);
    }
  }
  // #endregion
}
