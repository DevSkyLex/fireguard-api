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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanFixtures extends Fixture implements FixtureGroupInterface
{
  public const string FREE_PLAN_ID = '22222222-2222-4222-8222-222222222221';

  public const string PRO_PLAN_ID = '22222222-2222-4222-8222-222222222222';

  public const string MAX_PLAN_ID = '22222222-2222-4222-8222-222222222223';

  public static function getGroups(): array
  {
    return ['plan', 'main-seed'];
  }

  public function load(ObjectManager $manager): void
  {
    $createdAt = new DateTimeImmutable('2026-06-19T12:00:00+00:00');

    $plans = [
      [self::FREE_PLAN_ID, 'free', 'Free', 'Get started with the essentials', ['members' => 5, 'facilities' => 2, 'equipment' => 50, 'inspections' => 100], true, 1],
      [self::PRO_PLAN_ID, 'pro', 'Pro', 'Room to grow for active teams', ['members' => 50, 'facilities' => 25, 'equipment' => 2000, 'inspections' => 5000], false, 2],
      [self::MAX_PLAN_ID, 'max', 'Max', 'Maximum headroom for large organizations', ['members' => 250, 'facilities' => 125, 'equipment' => 10000, 'inspections' => 25000], false, 3],
    ];

    foreach ($plans as [$id, $key, $name, $description, $limits, $isDefault, $sortOrder]) {
      $plan = new PlanRecord();
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
}
