<?php

declare(strict_types=1);

namespace Organization\Infrastructure\DataFixtures;

use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Persistence\ObjectManager;
use Organization\Infrastructure\Persistence\Doctrine\Record\PlanRecord;

/**
 * Seeds the subscription plan catalog (Free, Pro, Max).
 *
 * @category DataFixtures
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanFixtures extends Fixture implements FixtureGroupInterface
{
  // #region Constants
  /**
   * Free subscription plan fixture identifier.
   */
  public const string FREE_PLAN_ID = '22222222-2222-4222-8222-222222222221';

  /**
   * Pro subscription plan fixture identifier.
   */
  public const string PRO_PLAN_ID = '22222222-2222-4222-8222-222222222222';

  /**
   * Max subscription plan fixture identifier.
   */
  public const string MAX_PLAN_ID = '22222222-2222-4222-8222-222222222223';
  // #endregion

  // #region Methods
  /**
   * Method getGroups.
   *
   * Returns the fixture groups used to load the plan catalog.
   *
   * @access public
   *
   * @static
   *
   * @return list<string> fixture group names
   */
  public static function getGroups(): array
  {
    return ['plan', 'main-seed'];
  }

  /**
   * Method load.
   *
   * Seeds the Free, Pro, and Max plans with their configured limits.
   *
   * @access public
   *
   * @param ObjectManager $manager the fixture object manager
   *
   * @return void no return value
   */
  public function load(ObjectManager $manager): void
  {
    $createdAt = new DateTimeImmutable('2026-06-19T12:00:00+00:00');

    $plans = [
      [self::FREE_PLAN_ID, 'free', 'Free', 'Get started with the essentials', ['members' => 5, 'facilities' => 2, 'equipment' => 50, 'inspections' => 100], true, 1],
      [self::PRO_PLAN_ID, 'pro', 'Pro', 'Room to grow for active teams', ['members' => 50, 'facilities' => 25, 'equipment' => 2000, 'inspections' => 5000], false, 2],
      [self::MAX_PLAN_ID, 'max', 'Max', 'Maximum headroom for large organizations', ['members' => 250, 'facilities' => 125, 'equipment' => 10000, 'inspections' => 25000], false, 3],
    ];

    foreach ($plans as [$id, $key, $name, $description, $limits, $isDefault, $sortOrder]) {
      // Guards against a duplicate-key violation when this fixture set is
      // loaded more than once against the same database (e.g. re-seeding a
      // long-lived environment): every other fixture here relies on
      // DAMA\DoctrineTestBundle's per-test rollback for isolation, but that
      // guarantee does not extend to non-test seed runs.
      $plan = $manager->find(PlanRecord::class, $id) ?? new PlanRecord();
      $plan->id = $id;
      $plan->key = $key;
      $plan->name = $name;
      $plan->description = $description;
      $plan->limits = $limits;
      $plan->isActive = true;
      $plan->isDefault = $isDefault;
      $plan->sortOrder = $sortOrder;
      $plan->createdAt = $createdAt;
      $plan->updatedAt = $createdAt;
      $manager->persist($plan);
    }

    $manager->flush();
  }
  // #endregion
}
