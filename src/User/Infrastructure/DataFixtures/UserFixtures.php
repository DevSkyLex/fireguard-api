<?php

declare(strict_types=1);

namespace User\Infrastructure\DataFixtures;

use Authorization\Domain\ValueObject\SubjectType;
use Authorization\Infrastructure\DataFixtures\AuthorizationFixtures;
use Authorization\Infrastructure\Persistence\Doctrine\Record\{RoleAssignmentRecord, RoleRecord};
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Shared\Domain\ValueObject\{Email, TenantId};
use Shared\Infrastructure\Service\UuidEventIdProvider;
use Shared\Infrastructure\Symfony\Adapter\Outbound\UuidGeneratorAdapter;
use Symfony\Component\Uid\Uuid;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, UserStatus, Username};
use User\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;

use function password_hash;
use function sprintf;

use const PASSWORD_BCRYPT;

/**
 * Fixtures UserFixtures.
 *
 * Loads sample users into the database.
 *
 * @category DataFixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class UserFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
  // #region Constants
  public const string ADMIN_USER_REFERENCE = 'admin-user';

  public const string TEST_USER_REFERENCE = 'test-user';

  private const string ADMIN_USER_ID = 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890';
  // #endregion

  // #region Constructor
  public function __construct(
    private readonly UserMapper $userMapper,
  ) {
  }
  // #endregion

  // #region Methods
  public static function getGroups(): array
  {
    return ['user', 'auth-seed'];
  }

  /**
   * @return array<class-string<Fixture>>
   */
  public function getDependencies(): array
  {
    return [
      AuthorizationFixtures::class,
    ];
  }

  public function load(ObjectManager $manager): void
  {
    // Create Admin User
    $adminUser = $this->createUser(
      id: self::ADMIN_USER_ID,
      username: 'admin',
      email: 'admin@fireguard.local',
      firstName: 'Admin',
      lastName: 'User',
      password: password_hash('Admin123!', PASSWORD_BCRYPT),
    );
    $adminRecord = $this->userMapper->toRecord($adminUser);
    $adminRecord->status = UserStatus::ACTIVE->value;
    $adminRecord->emailVerified = true;
    $manager->persist($adminRecord);
    $this->addReference(self::ADMIN_USER_REFERENCE, $adminRecord);
    $this->assignAdminRole($manager);

    // Create Test User
    $testUser = $this->createUser(
      id: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
      username: 'testuser',
      email: 'test@fireguard.local',
      firstName: 'Test',
      lastName: 'User',
      password: password_hash('Test123!', PASSWORD_BCRYPT),
    );
    $testRecord = $this->userMapper->toRecord($testUser);
    $testRecord->status = UserStatus::ACTIVE->value;
    $testRecord->emailVerified = true;
    $manager->persist($testRecord);
    $this->addReference(self::TEST_USER_REFERENCE, $testRecord);

    // Create Demo Users
    $demoUsers = [
      ['john_doe', 'john.doe@example.com', 'John', 'Doe'],
      ['jane_smith', 'jane.smith@example.com', 'Jane', 'Smith'],
      ['bob_wilson', 'bob.wilson@example.com', 'Bob', 'Wilson'],
    ];

    foreach ($demoUsers as $index => $userData) {
      $user = $this->createUser(
        id: sprintf('c3d4e5f6-a7b8-40%02d-8def-345678901234', $index),
        username: $userData[0],
        email: $userData[1],
        firstName: $userData[2],
        lastName: $userData[3],
        password: password_hash('Demo123!', PASSWORD_BCRYPT),
      );
      $record = $this->userMapper->toRecord($user);
      $manager->persist($record);
    }

    // Create User matching Dev Client ID (for Client Credentials flow)
    $clientUser = $this->createUser(
      id: 'a7b8c9d0-e1f2-4456-8123-789012345678',
      username: 'dev_client',
      email: 'dev.client@fireguard.local',
      firstName: 'Dev',
      lastName: 'Client',
      password: password_hash('DevClient123!', PASSWORD_BCRYPT),
    );
    $clientUserRecord = $this->userMapper->toRecord($clientUser);
    $manager->persist($clientUserRecord);

    $manager->flush();
  }

  private function createUser(
    string $id,
    string $username,
    string $email,
    string $firstName,
    string $lastName,
    string $password,
  ): User {
    $user = User::register(
      id: new UserId($id),
      username: new Username($username),
      email: new Email($email),
      password: new HashedPassword($password),
      profile: new UserProfile(
        firstName: $firstName,
        lastName: $lastName,
      ),
      tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
      eventIdProvider: new UuidEventIdProvider(new UuidGeneratorAdapter()),
    );

    // Clear domain events to avoid handler issues during fixtures
    $user->releaseEvents();

    return $user;
  }

  private function assignAdminRole(ObjectManager $manager): void
  {
    /** @var RoleRecord $adminRole */
    $adminRole = $this->getReference(AuthorizationFixtures::ROLE_ADMIN, RoleRecord::class);

    $assignment = new RoleAssignmentRecord();
    $assignment->id = Uuid::v7()->toRfc4122();
    $assignment->role = $adminRole;
    $assignment->roleId = $adminRole->id;
    $assignment->subjectType = SubjectType::USER->value;
    $assignment->subjectId = self::ADMIN_USER_ID;
    $assignment->assignedAt = new DateTimeImmutable();

    $manager->persist($assignment);
  }
  // #endregion
}
