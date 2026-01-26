<?php

declare(strict_types=1);

namespace Tests\Integration\User\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use User\Infrastructure\DataFixtures\UserFixtures;
use User\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

/**
 * Test UserFixturesIntegrationTest.
 *
 * @category Integration Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: UserFixtures::class)]
final class UserFixturesIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.entity_manager');
    $this->entityManager = $entityManager;

  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testLoadPersistsUsersAndReferences(): void
  {
    $fixtures = new UserFixtures(new UserMapper());
    $fixtures->setReferenceRepository(new ReferenceRepository($this->entityManager));

    $fixtures->load($this->entityManager);

    $count = $this->entityManager->getRepository(UserRecord::class)->count([]);
    self::assertSame(6, $count);

    self::assertTrue($fixtures->hasReference(UserFixtures::ADMIN_USER_REFERENCE, UserRecord::class));
    self::assertTrue($fixtures->hasReference(UserFixtures::TEST_USER_REFERENCE, UserRecord::class));

    /** @var UserRecord $admin */
    $admin = $fixtures->getReference(UserFixtures::ADMIN_USER_REFERENCE, UserRecord::class);

    self::assertSame('admin', $admin->username);
  }
  // #endregion
}
