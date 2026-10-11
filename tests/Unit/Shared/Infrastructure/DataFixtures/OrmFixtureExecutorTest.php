<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\SeedStepPort;
use Shared\Infrastructure\DataFixtures\OrmFixtureExecutor;

/**
 * Class OrmFixtureExecutorTest
 *
 * Invalid capability graphs fail before any database purge or owner seed invocation.
 *
 * @category Tests
 */
#[CoversClass(OrmFixtureExecutor::class)]
final class OrmFixtureExecutorTest extends TestCase
{
  #[Test]
  public function testAMissingPrerequisiteFailsBeforeTouchingPersistence(): void
  {
    $this->assertRejected([$this->step('equipment', ['facility'])]);
  }

  #[Test]
  public function testACycleFailsBeforeTouchingPersistence(): void
  {
    $this->assertRejected([$this->step('equipment', ['facility']), $this->step('facility', ['equipment'])]);
  }

  #[Test]
  public function testDuplicateCapabilityKeysFailBeforeTouchingPersistence(): void
  {
    $this->assertRejected([$this->step('equipment'), $this->step('equipment')]);
  }

  /**
   * @param list<FixtureInterface&SeedStepPort> $steps invalid seed capabilities
   */
  private function assertRejected(array $steps): void
  {
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::never())->method('getConnection');
    $this->expectException(LogicException::class);
    new OrmFixtureExecutor()->execute($manager, $steps);
  }

  /**
   * @param list<string> $dependencies prerequisite identities
   */
  private function step(string $key, array $dependencies = []): FixtureInterface&SeedStepPort
  {
    /** @var FixtureInterface&SeedStepPort&\PHPUnit\Framework\MockObject\MockObject $step */
    $step = $this->createMockForIntersectionOfInterfaces([FixtureInterface::class, SeedStepPort::class]);
    $step->method('key')->willReturn($key);
    $step->method('dependencies')->willReturn($dependencies);
    $step->expects(self::never())->method('load');
    $step->expects(self::never())->method('seed');

    return $step;
  }
}
