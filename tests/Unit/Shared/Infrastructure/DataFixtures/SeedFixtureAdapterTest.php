<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\{FixtureInterface, ReferenceRepository};
use Doctrine\Persistence\ObjectManager;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\SeedReferenceConsumerPort;
use Shared\Infrastructure\DataFixtures\{SeedFixtureAdapter, SeedReferenceRegistryAdapter};
use stdClass;

use function array_keys;
use function get_object_vars;

/**
 * Class SeedFixtureAdapterTest
 *
 * Protects owning database selection and scalar-only fixture publication.
 *
 * @category Tests
 */
#[CoversClass(SeedFixtureAdapter::class)]
#[CoversClass(SeedReferenceRegistryAdapter::class)]
final class SeedFixtureAdapterTest extends TestCase
{
  #[Test]
  public function testItPublishesOnlyPermittedScalarFactsAndSuppliesPrerequisiteReferences(): void
  {
    $manager = $this->createStub(ObjectManager::class);
    $registry = new SeedReferenceRegistryAdapter();
    /** @var FixtureInterface&SeedReferenceConsumerPort&\PHPUnit\Framework\MockObject\MockObject $fixture */
    $fixture = $this->createMockForIntersectionOfInterfaces([FixtureInterface::class, SeedReferenceConsumerPort::class]);
    $fixture->expects(self::once())->method('setSeedReferences')->with($registry);
    $fixture->expects(self::once())->method('load')->with($manager);
    $record = (object) ['id' => 'equipment-id', 'type' => 'camera', 'status' => 'operational', 'facilityId' => 'site-id', 'name' => 'Seed camera', 'password' => 'private-password', 'token' => 'private-token'];
    $references = $this->createStub(ReferenceRepository::class);
    $references->method('getReferencesByClass')->willReturn([stdClass::class => ['equipment-camera' => $record]]);
    $adapter = new SeedFixtureAdapter($fixture, $manager, $registry, 'equipment', ['facility']);
    $adapter->setReferenceRepository($references);

    $adapter->load($manager);

    self::assertSame('equipment', $adapter->key());
    self::assertSame(['facility'], $adapter->dependencies());
    $published = $registry->get('equipment-camera');
    self::assertSame(['id', 'type', 'status', 'facilityId', 'name'], array_keys(get_object_vars($published)));
    self::assertSame('equipment-id', $published->id);
    self::assertSame('camera', $published->type);
    self::assertSame('site-id', $published->facilityId);
  }

  #[Test]
  public function testItRefusesAnotherDatabaseBeforeRunningTheOwnerFixture(): void
  {
    $fixture = $this->createMock(FixtureInterface::class);
    $fixture->expects(self::never())->method('load');
    $adapter = new SeedFixtureAdapter($fixture, $this->createStub(ObjectManager::class), new SeedReferenceRegistryAdapter(), 'equipment');

    $this->expectException(LogicException::class);
    $adapter->load($this->createStub(ObjectManager::class));
  }
}
