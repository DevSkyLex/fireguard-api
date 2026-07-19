<?php

declare(strict_types=1);

namespace Tests\Integration\Onboarding\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\ORM\EntityManagerInterface;
use Onboarding\Domain\ValueObject\{OrganizationOnboardingState, OrganizationOnboardingStep};
use Onboarding\Infrastructure\DataFixtures\OnboardingFixtures;
use Onboarding\Infrastructure\Persistence\Doctrine\Record\OrganizationOnboardingSessionRecord;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(className: OnboardingFixtures::class)]
final class OnboardingFixturesIntegrationTest extends KernelTestCase
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
  public function testLoadPersistsCompletedAdminOnboardingSession(): void
  {
    /** @var OrganizationFixtures $organizationFixtures */
    $organizationFixtures = static::getContainer()->get(OrganizationFixtures::class);
    /** @var OnboardingFixtures $onboardingFixtures */
    $onboardingFixtures = static::getContainer()->get(OnboardingFixtures::class);

    $loader = new Loader();
    $loader->addFixture($organizationFixtures);
    $loader->addFixture($onboardingFixtures);

    $executor = new ORMExecutor($this->entityManager);
    $executor->execute($loader->getFixtures(), true);

    self::assertSame(1, $this->entityManager->getRepository(OrganizationOnboardingSessionRecord::class)->count([]));
    self::assertTrue($onboardingFixtures->hasReference(OnboardingFixtures::ADMIN_SESSION_REFERENCE, OrganizationOnboardingSessionRecord::class));

    /** @var OrganizationOnboardingSessionRecord|null $session */
    $session = $this->entityManager->getRepository(OrganizationOnboardingSessionRecord::class)->findOneBy([
      'userId' => 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
    ]);

    self::assertInstanceOf(OrganizationOnboardingSessionRecord::class, $session);
    self::assertSame('a1b2c3d4-e5f6-4890-8bcd-ef1234567890', $session->userId);
    self::assertSame('organization', $session->flow);
    self::assertSame(OrganizationOnboardingState::COMPLETED, $session->state);
    self::assertNull($session->nextStep);
    self::assertSame(OrganizationFixtures::ORGANIZATION_ID, $session->targetOrganizationId);
    self::assertSame('Fireguard Seed Organization', $session->targetOrganizationName);
    self::assertSame(OrganizationOnboardingStep::all(), $session->completedSteps);
    self::assertCount(4, $session->stepHistory);
  }
}
