<?php

declare(strict_types=1);

namespace Tests\Integration\User\Infrastructure\DataFixtures;

use Authorization\Domain\ValueObject\SubjectType;
use Authorization\Infrastructure\Catalog\RoleCatalog;
use Authorization\Infrastructure\DataFixtures\AuthorizationFixtures;
use Authorization\Infrastructure\Persistence\Doctrine\Record\{RoleAssignmentRecord, RoleRecord};
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use User\Domain\ValueObject\UserStatus;
use User\Infrastructure\DataFixtures\UserFixtures;
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
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
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
    /** @var UserFixtures $fixtures */
    $fixtures = static::getContainer()->get(UserFixtures::class);

    $loader = new Loader();
    $loader->addFixture(new AuthorizationFixtures());
    $loader->addFixture($fixtures);

    $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
    // Purge before loading: the test databases carry the seeded baseline, so
    // appending on top of it collides on primary keys and makes the counts
    // below meaningless. DAMA rolls the purge back with the rest of the test.
    $executor->execute($loader->getFixtures(), false);

    self::assertSame(6, $this->entityManager->getRepository(UserRecord::class)->count([]));

    self::assertTrue($fixtures->hasReference(UserFixtures::ADMIN_USER_REFERENCE, UserRecord::class));
    self::assertTrue($fixtures->hasReference(UserFixtures::TEST_USER_REFERENCE, UserRecord::class));

    /** @var UserRecord $admin */
    $admin = $fixtures->getReference(UserFixtures::ADMIN_USER_REFERENCE, UserRecord::class);
    /** @var UserRecord $testUser */
    $testUser = $fixtures->getReference(UserFixtures::TEST_USER_REFERENCE, UserRecord::class);

    self::assertSame('admin', $admin->username);
    self::assertSame(UserStatus::ACTIVE->value, $admin->status);
    self::assertTrue($admin->emailVerified);
    self::assertSame('testuser', $testUser->username);
    self::assertSame(UserStatus::ACTIVE->value, $testUser->status);
    self::assertTrue($testUser->emailVerified);

    $assignment = $this->entityManager->getRepository(RoleAssignmentRecord::class)->findOneBy([
      'subjectType' => SubjectType::USER->value,
      'subjectId' => $admin->id,
    ]);

    self::assertNotNull($assignment);
    self::assertInstanceOf(RoleRecord::class, $assignment->role);
    self::assertSame('admin', $assignment->role->name);

    $permissionNames = $assignment->role->permissions
      ->map(static fn ($permission): string => $permission->name)
      ->toArray();

    foreach (RoleCatalog::adminPermissionNames() as $permissionName) {
      self::assertContains($permissionName, $permissionNames);
    }
  }
  // #endregion
}
