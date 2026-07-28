<?php

declare(strict_types=1);

namespace Tests\Integration\Authorization\Infrastructure\DataFixtures;

use Authorization\Domain\ValueObject\SubjectType;
use Authorization\Infrastructure\DataFixtures\AuthorizationFixtures;
use Authorization\Infrastructure\Persistence\Doctrine\Record\{PermissionRecord, RoleAssignmentRecord, RoleRecord};
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test AuthorizationFixturesIntegrationTest.
 *
 * @category Integration Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: AuthorizationFixtures::class)]
final class AuthorizationFixturesIntegrationTest extends KernelTestCase
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
  public function testLoadCreatesDefaultRolesAndPermissions(): void
  {
    $fixtures = new AuthorizationFixtures();

    // Purge before loading: the test databases carry the seeded baseline, so
    // loading on top of it collides on primary keys and makes the counts below
    // meaningless. DAMA rolls the purge back with the rest of the test.
    new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager))->execute([$fixtures], false);

    $permissionCount = $this->entityManager->getRepository(PermissionRecord::class)->count([]);
    $roleCount = $this->entityManager->getRepository(RoleRecord::class)->count([]);
    $assignmentCount = $this->entityManager->getRepository(RoleAssignmentRecord::class)->count([]);

    self::assertSame(44, $permissionCount);
    self::assertSame(3, $roleCount);
    self::assertSame(1, $assignmentCount);

    self::assertTrue($fixtures->hasReference(AuthorizationFixtures::ROLE_SUPER_ADMIN, RoleRecord::class));
    self::assertTrue($fixtures->hasReference(AuthorizationFixtures::ROLE_ADMIN, RoleRecord::class));
    self::assertTrue($fixtures->hasReference(AuthorizationFixtures::ROLE_USER, RoleRecord::class));

    $assignment = $this->entityManager->getRepository(RoleAssignmentRecord::class)->findOneBy([
      'subjectId' => 'a7b8c9d0-e1f2-4456-8123-789012345678',
    ]);

    self::assertNotNull($assignment);
    self::assertSame(SubjectType::USER->value, $assignment->subjectType);
  }
  // #endregion
}
