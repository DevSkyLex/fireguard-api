<?php

declare(strict_types=1);

namespace Tests\Functional\Console;

use Authorization\Infrastructure\DataFixtures\AuthorizationFixtures;
use Equipment\Infrastructure\DataFixtures\EquipmentFixtures;
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Inspection\Infrastructure\DataFixtures\InspectionFixtures;
use OAuth\Infrastructure\DataFixtures\ClientFixtures;
use Onboarding\Infrastructure\DataFixtures\OnboardingFixtures;
use Organization\Infrastructure\DataFixtures\{OrganizationFixtures, PlanFixtures};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use ReflectionClass;
use Shared\Infrastructure\Console\LoadSeedFixturesCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use User\Infrastructure\DataFixtures\UserFixtures;

use function array_map;
use function get_debug_type;
use function is_iterable;
use function is_object;
use function sort;

#[CoversClass(LoadSeedFixturesCommand::class)]
final class LoadSeedFixturesCommandTest extends KernelTestCase
{
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

    $authFixtures = [...$authFixturesValue];
    $mainFixtures = [...$mainFixturesValue];

    $authFixtureTypes = array_map(static fn (mixed $fixture): string => is_object($fixture) ? $fixture::class : get_debug_type($fixture), $authFixtures);
    $mainFixtureTypes = array_map(static fn (mixed $fixture): string => is_object($fixture) ? $fixture::class : get_debug_type($fixture), $mainFixtures);
    sort($authFixtureTypes);
    sort($mainFixtureTypes);

    self::assertSame([
      AuthorizationFixtures::class,
      ClientFixtures::class,
      UserFixtures::class,
    ], $authFixtureTypes, 'Auth seed fixtures should come from the auth tag set.');

    self::assertSame([
      EquipmentFixtures::class,
      FacilityFixtures::class,
      InspectionFixtures::class,
      OnboardingFixtures::class,
      OrganizationFixtures::class,
      PlanFixtures::class,
    ], $mainFixtureTypes, 'Main seed fixtures should come from the main tag set.');

    foreach ([...$authFixtures, ...$mainFixtures] as $fixture) {
      self::assertIsObject($fixture);
      self::assertStringContainsString('Fixtures', get_debug_type($fixture));
    }
  }
}
