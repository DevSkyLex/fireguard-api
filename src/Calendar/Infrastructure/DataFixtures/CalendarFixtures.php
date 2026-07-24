<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\DataFixtures;

use Calendar\Infrastructure\Persistence\Doctrine\Record\CalendarEventRecord;
use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationMemberRecord;
use Shared\Infrastructure\DataFixtures\SeedTimeline;

use function sprintf;

/**
 * DataFixtures CalendarFixtures.
 *
 * Seeds organization calendar events straddling "today" so the calendar
 * feed renders a populated month — past, current and upcoming entries —
 * instead of an empty grid. Offsets are relative to now on purpose: a
 * hardcoded month would empty the calendar again a few weeks later.
 *
 * The feed also aggregates inspections, maintenance and interventions
 * (see `CalendarFeedAggregator`); these rows are the organization's own
 * manually created events, which have no other source.
 *
 * @category DataFixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
  // #region Constants
  /**
   * Constant EVENT_SEEDS.
   *
   * Day offsets are relative to "now"; a null facility reference means an
   * organization-wide event.
   *
   * @since 1.0.0
   *
   * @var list<array{
   *   title: string,
   *   description: string,
   *   dayOffset: int,
   *   startHour: int,
   *   durationHours: int,
   *   allDay: bool,
   *   facilityReference: ?string
   * }>
   */
  private const array EVENT_SEEDS = [
    ['title' => 'Quarterly fire drill', 'description' => 'Full evacuation drill with the local fire brigade.', 'dayOffset' => -12, 'startHour' => 9, 'durationHours' => 2, 'allDay' => false, 'facilityReference' => FacilityFixtures::SITE_REFERENCE],
    ['title' => 'Extinguisher recharge round', 'description' => 'Vendor visit to recharge the flagged extinguishers.', 'dayOffset' => -6, 'startHour' => 14, 'durationHours' => 3, 'allDay' => false, 'facilityReference' => FacilityFixtures::BUILDING_REFERENCE],
    ['title' => 'Safety committee monthly review', 'description' => 'Review of open non-conformities and overdue equipment.', 'dayOffset' => -2, 'startHour' => 10, 'durationHours' => 1, 'allDay' => false, 'facilityReference' => null],
    ['title' => 'Server room suppression test', 'description' => 'Annual test of the server room suppression system.', 'dayOffset' => 1, 'startHour' => 8, 'durationHours' => 4, 'allDay' => false, 'facilityReference' => FacilityFixtures::AREA_REFERENCE],
    ['title' => 'Lyon site audit', 'description' => 'On-site regulatory audit of the Lyon logistics hub.', 'dayOffset' => 3, 'startHour' => 9, 'durationHours' => 8, 'allDay' => true, 'facilityReference' => FacilityFixtures::LYON_SITE_REFERENCE],
    ['title' => 'New staff safety induction', 'description' => 'Evacuation routes and extinguisher handling for new joiners.', 'dayOffset' => 5, 'startHour' => 11, 'durationHours' => 2, 'allDay' => false, 'facilityReference' => FacilityFixtures::FLOOR_ONE_REFERENCE],
    ['title' => 'Marseille depot sprinkler survey', 'description' => 'Coverage survey ahead of the warehouse reorganization.', 'dayOffset' => 9, 'startHour' => 9, 'durationHours' => 6, 'allDay' => false, 'facilityReference' => FacilityFixtures::MARSEILLE_SITE_REFERENCE],
    ['title' => 'Alarm panel firmware upgrade', 'description' => 'Planned downtime on the Zone B alarm panel.', 'dayOffset' => 14, 'startHour' => 18, 'durationHours' => 3, 'allDay' => false, 'facilityReference' => FacilityFixtures::ZONE_B_REFERENCE],
    ['title' => 'Bordeaux training day', 'description' => 'Annual refresher for the regional safety officers.', 'dayOffset' => 18, 'startHour' => 9, 'durationHours' => 8, 'allDay' => true, 'facilityReference' => FacilityFixtures::BORDEAUX_SITE_REFERENCE],
    ['title' => 'Lille distribution centre walkthrough', 'description' => 'Pre-inspection walkthrough with the site manager.', 'dayOffset' => 22, 'startHour' => 10, 'durationHours' => 3, 'allDay' => false, 'facilityReference' => FacilityFixtures::LILLE_SITE_REFERENCE],
  ];
  // #endregion

  // #region Methods
  public static function getGroups(): array
  {
    return ['calendar', 'main-seed'];
  }

  public function getDependencies(): array
  {
    return [
      OrganizationFixtures::class,
      FacilityFixtures::class,
    ];
  }

  public function load(ObjectManager $manager): void
  {
    /** @var OrganizationMemberRecord $ownerMember */
    $ownerMember = $this->getReference(OrganizationFixtures::OWNER_MEMBER_REFERENCE, OrganizationMemberRecord::class);

    foreach (self::EVENT_SEEDS as $index => $seed) {
      $startsAt = SeedTimeline::fromNow($seed['dayOffset'], $seed['startHour']);

      $event = new CalendarEventRecord();
      $event->id = sprintf('77777777-7777-4777-8777-7777777777%02x', $index);
      $event->organizationId = OrganizationFixtures::ORGANIZATION_ID;
      $event->title = $seed['title'];
      $event->description = $seed['description'];
      $event->startsAt = $startsAt;
      $event->endsAt = $startsAt->modify(sprintf('+%d hours', $seed['durationHours']));
      $event->allDay = $seed['allDay'];
      $event->facilityId = null === $seed['facilityReference']
        ? null
        : $this->getReference($seed['facilityReference'], FacilityRecord::class)->id;
      $event->createdByMemberId = $ownerMember->id;
      $event->createdAt = $startsAt->modify('-21 days');
      $event->updatedAt = $startsAt->modify('-21 days');

      $manager->persist($event);
    }

    $manager->flush();
  }
  // #endregion
}
