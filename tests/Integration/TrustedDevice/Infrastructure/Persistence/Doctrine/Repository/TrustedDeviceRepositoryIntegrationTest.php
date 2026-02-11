<?php

declare(strict_types=1);

namespace Tests\Integration\TrustedDevice\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use TrustedDevice\Domain\Model\TrustedDevice\TrustedDevice;
use TrustedDevice\Domain\ValueObject\{DeviceFingerprint, DeviceToken, TrustedDeviceId};
use TrustedDevice\Infrastructure\Persistence\Doctrine\Mapper\TrustedDeviceMapper;
use TrustedDevice\Infrastructure\Persistence\Doctrine\Repository\TrustedDeviceRepository;

/**
 * Test TrustedDeviceRepositoryIntegrationTest.
 *
 * @category Integration Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TrustedDeviceRepository::class)]
final class TrustedDeviceRepositoryIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;

  private TrustedDeviceRepository $repository;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;

    $this->repository = new TrustedDeviceRepository(
      entityManager: $this->entityManager,
      mapper: new TrustedDeviceMapper(),
    );
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testSaveAndFindById(): void
  {
    $device = $this->createTrustedDevice(
      id: '123e4567-e89b-12d3-a456-426614174000',
      userId: 'user-1',
    );

    $this->repository->save($device);

    $found = $this->repository->findById(new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174000'));

    self::assertNotNull($found);
    self::assertSame('user-1', $found->userId());
    self::assertTrue($found->isValid());
  }

  #[Test]
  public function testFindByUserIdAndFingerprintReturnsDevice(): void
  {
    $device = $this->createTrustedDevice(
      id: '123e4567-e89b-12d3-a456-426614174010',
      userId: 'user-2',
    );

    $this->repository->save($device);

    $found = $this->repository->findByUserIdAndFingerprint('user-2', $device->fingerprint()->value);

    self::assertNotNull($found);
    self::assertSame($device->id()->value, $found->id()->value);
  }

  #[Test]
  public function testFindByTokenIgnoresExpiredDevices(): void
  {
    $active = $this->createTrustedDevice(
      id: '123e4567-e89b-12d3-a456-426614174020',
      userId: 'user-3',
    );
    $expiredToken = DeviceToken::generate()->hash;
    $fingerprint = DeviceFingerprint::create('Mozilla/5.0', '127.0.0.1');
    $expired = TrustedDevice::reconstitute(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174021'),
      userId: 'user-3',
      tokenHash: $expiredToken,
      fingerprint: $fingerprint,
      name: $fingerprint->getDeviceName(),
      lastUsedAt: new DateTimeImmutable('-2 days'),
      expiresAt: new DateTimeImmutable('-1 day'),
      createdAt: new DateTimeImmutable('-3 days'),
      revoked: false,
    );

    $this->repository->save($active);
    $this->repository->save($expired);

    self::assertNotNull($this->repository->findByToken($active->token()->hash));
    self::assertNull($this->repository->findByToken($expiredToken));
  }

  #[Test]
  public function testFindAllByUserIdReturnsOnlyActiveDevices(): void
  {
    $active = $this->createTrustedDevice(
      id: '123e4567-e89b-12d3-a456-426614174030',
      userId: 'user-4',
    );
    $revoked = $this->createTrustedDevice(
      id: '123e4567-e89b-12d3-a456-426614174031',
      userId: 'user-4',
    );
    $revoked->revoke();

    $this->repository->save($active);
    $this->repository->save($revoked);

    $devices = $this->repository->findAllByUserId('user-4');

    self::assertCount(1, $devices);
    self::assertSame($active->id()->value, $devices[0]->id()->value);
  }

  #[Test]
  public function testRevokeAllForUserMarksDevicesRevoked(): void
  {
    $deviceOne = $this->createTrustedDevice(
      id: '123e4567-e89b-12d3-a456-426614174040',
      userId: 'user-5',
    );
    $deviceTwo = $this->createTrustedDevice(
      id: '123e4567-e89b-12d3-a456-426614174041',
      userId: 'user-5',
    );

    $this->repository->save($deviceOne);
    $this->repository->save($deviceTwo);

    $count = $this->repository->revokeAllForUser('user-5');

    self::assertSame(2, $count);
    self::assertCount(0, $this->repository->findAllByUserId('user-5'));
  }

  #[Test]
  public function testDeleteRemovesDevice(): void
  {
    $device = $this->createTrustedDevice(
      id: '123e4567-e89b-12d3-a456-426614174050',
      userId: 'user-6',
    );

    $this->repository->save($device);

    $this->repository->delete(new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174050'));

    self::assertNull($this->repository->findById(new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174050')));
  }
  // #endregion

  // #region Helpers
  private function createTrustedDevice(string $id, string $userId): TrustedDevice
  {
    $fingerprint = DeviceFingerprint::create(
      userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
      ipAddress: '127.0.0.1',
      acceptLanguage: $id,
    );
    $device = TrustedDevice::trust(
      id: new TrustedDeviceId($id),
      userId: $userId,
      fingerprint: $fingerprint,
      ttlDays: 30,
    );
    $device->releaseEvents();

    return $device;
  }
  // #endregion
}
