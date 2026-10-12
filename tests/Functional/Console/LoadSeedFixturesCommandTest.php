<?php

declare(strict_types=1);

namespace Tests\Functional\Console;

use Authorization\Infrastructure\DataFixtures\AuthorizationFixtures;
use Calendar\Infrastructure\DataFixtures\CalendarFixtures;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\DataFixtures\EquipmentFixtures;
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Inspection\Infrastructure\DataFixtures\InspectionFixtures;
use Intervention\Infrastructure\DataFixtures\{InterventionFixtures, InterventionWorkloadFixtures};
use Maintenance\Infrastructure\DataFixtures\MaintenanceFixtures;
use Messaging\Infrastructure\DataFixtures\MessagingFixtures;
use OAuth\Infrastructure\DataFixtures\ClientFixtures;
use Onboarding\Infrastructure\DataFixtures\OnboardingFixtures;
use Organization\Infrastructure\DataFixtures\{OrganizationFixtures, PlanFixtures};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use ReflectionClass;
use Shared\Application\Port\Inbound\SeedStepPort;
use Shared\Infrastructure\Console\{AppendSeedFixturesCommand, LoadSeedFixturesCommand};
use Shared\Infrastructure\DataFixtures\{OrmFixtureExecutor, SeedFixtureAdapter};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use User\Infrastructure\DataFixtures\UserFixtures;
use Workload\Infrastructure\DataFixtures\WorkloadFixtures;

use function count;
use function is_iterable;
use function ksort;

#[CoversClass(LoadSeedFixturesCommand::class)]
final class LoadSeedFixturesCommandTest extends KernelTestCase
{
  #[Test]
  public function testAppendCommandReceivesOnlyOptInAppendSafeFixtures(): void
  {
    self::bootKernel();
    $command = self::getContainer()->get(AppendSeedFixturesCommand::class);
    $fixtures = new ReflectionClass($command)->getProperty('fixtures')->getValue($command);
    self::assertIsIterable($fixtures);
    $types = [];
    foreach ($fixtures as $fixture) {
      self::assertIsObject($fixture);
      $types[] = $fixture::class;
    }
    self::assertSame([WorkloadFixtures::class, InterventionWorkloadFixtures::class], $types);
  }

  #[Test]
  public function testCommandReceivesTaggedFixtureSetsFromContainer(): void
  {
    self::bootKernel();

    /** @var LoadSeedFixturesCommand $command */
    $command = self::getContainer()->get(LoadSeedFixturesCommand::class);

    $reflection = new ReflectionClass($command);
    $authProperty = $reflection->getProperty('authFixtures');
    $authProperty->setAccessible(true);
    $mainProperty = $reflection->getProperty('mainFixtures');
    $mainProperty->setAccessible(true);

    $authFixturesValue = $authProperty->getValue($command);
    $mainFixturesValue = $mainProperty->getValue($command);
    if (!is_iterable($authFixturesValue)) {
      self::fail('Auth fixtures property should be iterable.');
    }
    if (!is_iterable($mainFixturesValue)) {
      self::fail('Main fixtures property should be iterable.');
    }

    $authManager = self::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $authManager);
    $mainManager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $mainManager);

    $this->assertSeedCapabilities($authFixturesValue, $authManager, [
      'authorization' => [AuthorizationFixtures::class, []],
      'user' => [UserFixtures::class, ['authorization']],
      'oauth' => [ClientFixtures::class, []],
    ]);
    $this->assertSeedCapabilities($mainFixturesValue, $mainManager, [
      'plans' => [PlanFixtures::class, []],
      'organization' => [OrganizationFixtures::class, ['plans']],
      'onboarding' => [OnboardingFixtures::class, ['organization']],
      'facility' => [FacilityFixtures::class, ['organization']],
      'equipment' => [EquipmentFixtures::class, ['organization', 'facility']],
      'inspection' => [InspectionFixtures::class, ['organization', 'facility', 'equipment']],
      'maintenance' => [MaintenanceFixtures::class, ['organization', 'equipment']],
      'intervention' => [InterventionFixtures::class, ['organization', 'facility', 'equipment']],
      'calendar' => [CalendarFixtures::class, ['organization', 'facility']],
      'messaging' => [MessagingFixtures::class, ['organization']],
    ]);
  }

  /**
   * Verifies capability identities, owner delegates, database selection and dependency order.
   *
   * @param iterable<mixed> $fixtures tagged capabilities injected by the container
   * @param EntityManagerInterface $manager explicitly owning database
   * @param array<string, array{class-string<FixtureInterface>, list<string>}> $expected capability identities, local owners and prerequisites
   */
  private function assertSeedCapabilities(iterable $fixtures, EntityManagerInterface $manager, array $expected): void
  {
    $actual = [];
    $capabilities = [];
    $reflection = new ReflectionClass(SeedFixtureAdapter::class);
    foreach ($fixtures as $fixture) {
      self::assertInstanceOf(SeedStepPort::class, $fixture);
      self::assertInstanceOf(SeedFixtureAdapter::class, $fixture);
      $key = $fixture->key();
      self::assertArrayNotHasKey($key, $actual, 'Each capability must appear exactly once in its owning group.');
      $owner = $reflection->getProperty('fixture')->getValue($fixture);
      self::assertInstanceOf(FixtureInterface::class, $owner);
      self::assertSame($manager, $reflection->getProperty('manager')->getValue($fixture), 'Seed capabilities must retain their explicitly selected owning database.');
      $actual[$key] = [$owner::class, $fixture->dependencies()];
      $capabilities[] = $fixture;
    }

    ksort($expected);
    ksort($actual);
    self::assertSame($expected, $actual, 'Each tagged group must preserve all owner capabilities and prerequisites.');

    $executor = self::getContainer()->get(OrmFixtureExecutor::class);
    self::assertInstanceOf(OrmFixtureExecutor::class, $executor);
    $ordered = new ReflectionClass($executor)->getMethod('orderFixtures')->invoke($executor, $capabilities);
    self::assertIsArray($ordered);
    self::assertCount(count($capabilities), $ordered);
    $completed = [];
    foreach ($ordered as $fixture) {
      self::assertInstanceOf(SeedStepPort::class, $fixture);
      foreach ($fixture->dependencies() as $prerequisite) {
        self::assertArrayHasKey($prerequisite, $completed, 'The executor must plan prerequisites before their consumers.');
      }
      $completed[$fixture->key()] = true;
    }
  }
}
