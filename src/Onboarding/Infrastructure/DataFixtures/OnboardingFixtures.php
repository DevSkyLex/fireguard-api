<?php

declare(strict_types=1);

namespace Onboarding\Infrastructure\DataFixtures;

use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Onboarding\Domain\ValueObject\{OrganizationOnboardingState, OrganizationOnboardingStep};
use Onboarding\Infrastructure\Persistence\Doctrine\Record\OrganizationOnboardingSessionRecord;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;

final class OnboardingFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
  public const string ADMIN_SESSION_REFERENCE = 'onboarding-seed-admin-session';

  private const string ADMIN_USER_ID = 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890';

  public static function getGroups(): array
  {
    return ['onboarding', 'main-seed'];
  }

  public function getDependencies(): array
  {
    return [OrganizationFixtures::class];
  }

  public function load(ObjectManager $manager): void
  {
    $allSteps = OrganizationOnboardingStep::all();

    $stepHistory = [
      [
        'stepKey' => OrganizationOnboardingStep::CREATE_ORGANIZATION,
        'occurredAt' => '2026-02-01T09:00:00+00:00',
        'skipped' => false,
      ],
      [
        'stepKey' => OrganizationOnboardingStep::INVITE_MEMBERS,
        'occurredAt' => '2026-02-02T10:05:00+00:00',
        'skipped' => false,
      ],
      [
        'stepKey' => OrganizationOnboardingStep::CREATE_FIRST_FACILITY,
        'occurredAt' => '2026-03-03T09:00:00+00:00',
        'skipped' => false,
      ],
      [
        'stepKey' => OrganizationOnboardingStep::CREATE_FIRST_EQUIPMENT,
        'occurredAt' => '2026-03-06T08:30:00+00:00',
        'skipped' => false,
      ],
    ];

    $session = new OrganizationOnboardingSessionRecord();
    $session->id = '55555555-5555-4555-8555-555555555551';
    $session->userId = self::ADMIN_USER_ID;
    $session->flow = 'organization';
    $session->state = OrganizationOnboardingState::COMPLETED;
    $session->nextStep = null;
    $session->blockedReason = null;
    $session->targetOrganizationId = OrganizationFixtures::ORGANIZATION_ID;
    $session->targetOrganizationName = 'Fireguard Seed Organization';
    $session->completedSteps = $allSteps;
    $session->skippedSteps = [];
    $session->rollbackStack = [];
    $session->stepHistory = $stepHistory;
    $session->createdAt = new DateTimeImmutable('2026-02-01T09:00:00+00:00');
    $session->updatedAt = new DateTimeImmutable('2026-03-06T09:00:00+00:00');
    $manager->persist($session);
    $this->addReference(self::ADMIN_SESSION_REFERENCE, $session);

    $manager->flush();
  }
}
