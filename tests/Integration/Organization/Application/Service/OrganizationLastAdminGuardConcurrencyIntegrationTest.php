<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Application\Service;

use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Organization\Application\Service\{OrganizationAuthorizationService, OrganizationLastAdminGuardService};
use Organization\Domain\Exception\OrganizationLastAdminException;
use Organization\Domain\ValueObject\{OrganizationMemberId, OrganizationQuotaResource};
use Organization\Infrastructure\Persistence\Doctrine\Lock\PostgresOrganizationQuotaLockAdapter;
use Organization\Infrastructure\Persistence\Doctrine\Repository\{OrganizationMemberRepository, OrganizationRoleRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use RuntimeException;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

use function is_string;
use function sprintf;

/**
 * Test OrganizationLastAdminGuardConcurrencyIntegrationTest.
 *
 * Proves the last-administrator invariant survives two concurrent removals.
 * The guard reads the administrator census and the caller deactivates a member
 * afterwards; without serialization two requests sitting on an organization with
 * exactly two administrators both read "another administrator remains" and both
 * deactivate one, leaving nobody able to re-admit members. The guard closes that
 * by taking a transaction-scoped Postgres advisory lock before it reads.
 *
 * These tests need two genuinely independent connections, so they deliberately
 * bypass the container's Doctrine services: dama/doctrine-test-bundle shares one
 * rollback-only connection across the whole test, which cannot express two
 * overlapping transactions. Fixtures are therefore committed for real and torn
 * down explicitly.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OrganizationLastAdminGuardService::class)]
#[CoversClass(className: PostgresOrganizationQuotaLockAdapter::class)]
final class OrganizationLastAdminGuardConcurrencyIntegrationTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'a1c7f0de-0000-4000-8000-000000000001';

  private const string OTHER_ORGANIZATION_ID = 'a1c7f0de-0000-4000-8000-000000000002';

  private const string ADMIN_ROLE_ID = 'a1c7f0de-0000-4000-8000-000000000011';

  private const string FIRST_MEMBER_ID = 'a1c7f0de-0000-4000-8000-000000000021';

  private const string SECOND_MEMBER_ID = 'a1c7f0de-0000-4000-8000-000000000022';

  private const string FIRST_USER_ID = 'a1c7f0de-0000-4000-8000-000000000031';

  private const string SECOND_USER_ID = 'a1c7f0de-0000-4000-8000-000000000032';

  /**
   * SQLSTATE PostgreSQL raises when lock_timeout expires while waiting.
   */
  private const string LOCK_NOT_AVAILABLE_SQL_STATE = '55P03';

  private Connection $connectionA;

  private Connection $connectionB;

  private EntityManagerInterface $entityManagerA;

  private EntityManagerInterface $entityManagerB;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $containerEntityManager */
    $containerEntityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $configuration = $containerEntityManager->getConfiguration();

    $this->connectionA = $this->openConnection();
    $this->connectionB = $this->openConnection();
    $this->entityManagerA = new EntityManager($this->connectionA, $configuration);
    $this->entityManagerB = new EntityManager($this->connectionB, $configuration);

    $this->deleteFixtures();
    $this->seedOrganizationWithTwoAdministrators();
  }

  protected function tearDown(): void
  {
    foreach ([$this->connectionA, $this->connectionB] as $connection) {
      if ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }

    $this->deleteFixtures();

    $this->entityManagerA->close();
    $this->entityManagerB->close();
    $this->connectionA->close();
    $this->connectionB->close();

    parent::tearDown();
  }

  /**
   * Method testTwoOverlappingRemovalsCannotBothStripAnAdministrator.
   *
   * The race itself, in the order that used to strand an organization: request A
   * passes the check and deactivates the first administrator but has not
   * committed yet, so request B — reading its own snapshot — would still see two
   * administrators and happily remove the second. The lock has to stop B from
   * entering the check at all until A is done; only then may B read the committed
   * census, and it must refuse.
   *
   * Real concurrent requests simply block here, so B runs with a short
   * lock_timeout: without the lock this test does not time out, it sails through
   * the check and reports the organization it was about to strand.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testTwoOverlappingRemovalsCannotBothStripAnAdministrator(): void
  {
    $guardA = $this->buildGuard($this->entityManagerA);
    $guardB = $this->buildGuard($this->entityManagerB);
    $membersA = new OrganizationMemberRepository($this->entityManagerA);
    $membersB = new OrganizationMemberRepository($this->entityManagerB);

    // Request A: check, then deactivate the first administrator. Still open.
    $this->connectionA->beginTransaction();
    $guardA->assertCanRemoveMember(self::ORGANIZATION_ID, self::FIRST_MEMBER_ID);
    $firstMember = $membersA->findById(OrganizationMemberId::fromString(self::FIRST_MEMBER_ID));
    self::assertNotNull($firstMember);
    $firstMember->deactivate();
    $membersA->save($firstMember);

    // Request B arrives mid-flight and must not be allowed to run its census.
    $this->connectionB->executeStatement("SET lock_timeout = '250ms'");
    $this->connectionB->beginTransaction();

    $failure = null;

    try {
      $guardB->assertCanRemoveMember(self::ORGANIZATION_ID, self::SECOND_MEMBER_ID);
    } catch (Throwable $exception) {
      $failure = $exception;
    }

    $this->connectionB->rollBack();

    self::assertInstanceOf(
      DriverException::class,
      $failure,
      'The second removal read the administrator census while the first was mid-removal: '
      . 'both would have passed and left the organization with no administrator.',
    );
    // SQLSTATE 55P03 (lock_not_available) is what lock_timeout raises: B was
    // still waiting on the lock A holds, which is exactly the blocking a real
    // concurrent request would do.
    self::assertSame(self::LOCK_NOT_AVAILABLE_SQL_STATE, $failure->getSQLState());

    // A commits and releases the lock.
    $this->connectionA->commit();

    // B retries, now unobstructed, and must see the state A committed.
    $this->connectionB->beginTransaction();
    $refused = false;

    try {
      $guardB->assertCanRemoveMember(self::ORGANIZATION_ID, self::SECOND_MEMBER_ID);

      $secondMember = $membersB->findById(OrganizationMemberId::fromString(self::SECOND_MEMBER_ID));
      self::assertNotNull($secondMember);
      $secondMember->deactivate();
      $membersB->save($secondMember);
      $this->connectionB->commit();
    } catch (OrganizationLastAdminException) {
      $refused = true;
      $this->connectionB->rollBack();
    }

    self::assertTrue($refused, 'The surviving administrator must not be removable.');
    self::assertGreaterThanOrEqual(
      1,
      $this->countActiveAdministrators($this->connectionA),
      'The organization must never be left without an active administrator.',
    );
  }

  /**
   * Method testTheLockIsScopedToOneOrganization.
   *
   * A removal in one organization must not stall removals in another: the lock
   * is keyed per organization, so unrelated tenants never contend.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testTheLockIsScopedToOneOrganization(): void
  {
    $guardA = $this->buildGuard($this->entityManagerA);
    $lockB = new PostgresOrganizationQuotaLockAdapter($this->entityManagerB);

    $this->connectionA->beginTransaction();
    $guardA->assertCanRemoveMember(self::ORGANIZATION_ID, self::FIRST_MEMBER_ID);

    $this->connectionB->executeStatement("SET lock_timeout = '250ms'");
    $this->connectionB->beginTransaction();

    $failure = null;

    try {
      $lockB->acquire(self::OTHER_ORGANIZATION_ID, OrganizationQuotaResource::MEMBERS);
    } catch (Throwable $exception) {
      $failure = $exception;
    }

    $this->connectionB->rollBack();

    self::assertNull($failure, 'A different organization must acquire its own lock without waiting.');
    $this->connectionA->rollBack();
  }

  /**
   * Method openConnection.
   *
   * Opens a connection to the main test database outside the container, so the
   * shared rollback-only connection installed by dama/doctrine-test-bundle does
   * not collapse the two transactions these tests need into one.
   *
   * @since 1.0.0
   *
   * @return Connection the new connection
   */
  private function openConnection(): Connection
  {
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;

    if (!is_string($url) || '' === $url) {
      throw new RuntimeException('MAIN_DATABASE_URL must be set to run the concurrency tests.');
    }

    return DriverManager::getConnection(['url' => $url]);
  }

  /**
   * Method buildGuard.
   *
   * Wires the guard exactly as config/modules/organization.yaml does, on the
   * given entity manager, so the advisory lock rides the same connection as the
   * transaction under test.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager to wire against
   *
   * @return OrganizationLastAdminGuardService the guard service
   */
  private function buildGuard(EntityManagerInterface $entityManager): OrganizationLastAdminGuardService
  {
    $memberRepository = new OrganizationMemberRepository($entityManager);

    return new OrganizationLastAdminGuardService(
      $memberRepository,
      new OrganizationRoleRepository($entityManager),
      new OrganizationAuthorizationService($memberRepository),
      $this->nullEventDispatcher(),
      new PostgresOrganizationQuotaLockAdapter($entityManager),
    );
  }

  /**
   * Method nullEventDispatcher.
   *
   * The lockout-prevented event is audited elsewhere; these tests are about the
   * refusal itself.
   *
   * @since 1.0.0
   *
   * @return EventDispatcherPort a dispatcher that swallows every event
   */
  private function nullEventDispatcher(): EventDispatcherPort
  {
    return new class () implements EventDispatcherPort {
      public function dispatch(object $event): void
      {
      }

      public function dispatchAll(array $events): void
      {
      }
    };
  }

  /**
   * Method countActiveAdministrators.
   *
   * Counts the active members of the organization holding a role that grants
   * organization.members.manage.
   *
   * @since 1.0.0
   *
   * @param Connection $connection the connection to count on
   *
   * @return int the number of active administrators
   */
  private function countActiveAdministrators(Connection $connection): int
  {
    $count = $connection->fetchOne(
      <<<'SQL'
        SELECT COUNT(DISTINCT m.id)
        FROM organization_members m
        INNER JOIN organization_member_roles mr ON mr.member_id = m.id
        INNER JOIN organization_roles r ON r.id = mr.role_id
        WHERE m.organization_id = :organizationId
          AND m.is_active = TRUE
          AND r.permissions::text LIKE :permission
        SQL,
      [
        'organizationId' => self::ORGANIZATION_ID,
        'permission' => '%organization.members.manage%',
      ],
    );

    self::assertIsNumeric($count);

    return (int) $count;
  }

  /**
   * Method seedOrganizationWithTwoAdministrators.
   *
   * Commits an organization holding exactly two active administrators, plus a
   * second organization used to prove the lock is per-tenant.
   *
   * @since 1.0.0
   */
  private function seedOrganizationWithTwoAdministrators(): void
  {
    $now = new DateTimeImmutable()->format('Y-m-d H:i:s');

    foreach ([self::ORGANIZATION_ID => 'guard-race-org', self::OTHER_ORGANIZATION_ID => 'guard-race-org-b'] as $id => $slug) {
      $this->connectionA->insert('organizations', [
        'id' => $id,
        'name' => $slug,
        'slug' => $slug,
        'owner_user_id' => self::FIRST_USER_ID,
        'created_by_user_id' => self::FIRST_USER_ID,
        'status' => 'active',
        'is_active' => 'true',
        'settings' => '[]',
        'created_at' => $now,
        'updated_at' => $now,
      ]);
    }

    $this->connectionA->insert('organization_roles', [
      'id' => self::ADMIN_ROLE_ID,
      'organization_id' => self::ORGANIZATION_ID,
      'name' => 'race-admin',
      'permissions' => '["organization.members.manage"]',
      'description' => '',
      'is_system' => 'false',
      'created_at' => $now,
    ]);

    $members = [
      self::FIRST_MEMBER_ID => self::FIRST_USER_ID,
      self::SECOND_MEMBER_ID => self::SECOND_USER_ID,
    ];

    foreach ($members as $memberId => $userId) {
      $this->connectionA->insert('organization_members', [
        'id' => $memberId,
        'organization_id' => self::ORGANIZATION_ID,
        'user_id' => $userId,
        'is_active' => 'true',
        'joined_at' => $now,
      ]);
      $this->connectionA->insert('organization_member_roles', [
        'member_id' => $memberId,
        'role_id' => self::ADMIN_ROLE_ID,
        'assigned_at' => $now,
      ]);
    }

    self::assertSame(
      2,
      $this->countActiveAdministrators($this->connectionA),
      sprintf('The fixture must start with exactly two administrators in %s.', self::ORGANIZATION_ID),
    );
  }

  /**
   * Method deleteFixtures.
   *
   * Removes the committed fixtures. These rows live outside the test bundle's
   * rollback, so they have to be cleaned up by hand.
   *
   * @since 1.0.0
   */
  private function deleteFixtures(): void
  {
    $this->connectionA->executeStatement(
      'DELETE FROM organization_member_roles WHERE role_id = :roleId',
      ['roleId' => self::ADMIN_ROLE_ID],
    );
    $this->connectionA->executeStatement(
      'DELETE FROM organization_members WHERE organization_id IN (:ids)',
      ['ids' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['ids' => \Doctrine\DBAL\ArrayParameterType::STRING],
    );
    $this->connectionA->executeStatement(
      'DELETE FROM organization_roles WHERE organization_id IN (:ids)',
      ['ids' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['ids' => \Doctrine\DBAL\ArrayParameterType::STRING],
    );
    $this->connectionA->executeStatement(
      'DELETE FROM organizations WHERE id IN (:ids)',
      ['ids' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['ids' => \Doctrine\DBAL\ArrayParameterType::STRING],
    );
  }
}
