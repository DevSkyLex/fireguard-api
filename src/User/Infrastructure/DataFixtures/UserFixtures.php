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
use Shared\Infrastructure\DataFixtures\SeedUuid;
use Shared\Infrastructure\Service\UuidEventIdProvider;
use Shared\Infrastructure\Symfony\Adapter\Outbound\UuidGeneratorAdapter;
use Symfony\Component\Uid\Uuid;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, UserStatus, Username};
use User\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;

use function count;
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

  public const string NOVA_OWNER_REFERENCE = 'user-seed-nova-owner';

  public const string VIGILANCE_OWNER_REFERENCE = 'user-seed-vigilance-owner';

  public const string SAFEGUARD_OWNER_REFERENCE = 'user-seed-safeguard-owner';

  public const string PREVENTION_OWNER_REFERENCE = 'user-seed-prevention-owner';

  /**
   * Constant STAFF_PASSWORD.
   *
   * Shared plaintext credential for every seeded staff account, so a demo
   * can sign in as any of them without hunting through this file. Only the
   * `admin`/`testuser` accounts keep their own historical passwords.
   *
   * @since 1.1.0
   *
   * @var string
   */
  public const string STAFF_PASSWORD = 'Staff123!';

  /**
   * Constant STAFF_SEEDS.
   *
   * The FireGuard demo workforce. One row per account the organization
   * fixtures then turn into an organization member, so interventions,
   * inspections and work items have a realistic roster to be assigned to
   * instead of the same two accounts over and over.
   *
   * `status` deliberately covers every {@see UserStatus} case: a directory
   * that only ever contains active users never exercises the "cannot log in"
   * and "awaiting verification" paths the admin screens are built around.
   *
   * @since 1.1.0
   *
   * @var list<array{
   *   reference: string,
   *   id: string,
   *   username: string,
   *   email: string,
   *   firstName: string,
   *   lastName: string,
   *   status: UserStatus,
   *   emailVerified: bool,
   *   admin: bool
   * }>
   */
  public const array STAFF_SEEDS = [
    [
      'reference' => 'user-seed-safety-manager',
      'id' => '1a1891d0-f1a4-4392-8cf5-a6b2604873fc',
      'username' => 'marie_lefevre',
      'email' => 'marie.lefevre@fireguard.local',
      'firstName' => 'Marie',
      'lastName' => 'Lefèvre',
      'status' => UserStatus::ACTIVE,
      'emailVerified' => true,
      'admin' => true,
    ],
    [
      'reference' => 'user-seed-technician-paris',
      'id' => '8a181f64-6590-4365-94c4-14e313badf80',
      'username' => 'thomas_girard',
      'email' => 'thomas.girard@fireguard.local',
      'firstName' => 'Thomas',
      'lastName' => 'Girard',
      'status' => UserStatus::ACTIVE,
      'emailVerified' => true,
      'admin' => false,
    ],
    [
      'reference' => 'user-seed-technician-field',
      'id' => 'd2bbb340-23ce-46e5-be65-7e534970811e',
      'username' => 'sofia_moreau',
      'email' => 'sofia.moreau@fireguard.local',
      'firstName' => 'Sofia',
      'lastName' => 'Moreau',
      'status' => UserStatus::ACTIVE,
      'emailVerified' => true,
      'admin' => false,
    ],
    [
      'reference' => 'user-seed-regional-coordinator',
      'id' => '4a15e7c4-c5ff-4683-8df5-2b7c375cdada',
      'username' => 'karim_benali',
      'email' => 'karim.benali@fireguard.local',
      'firstName' => 'Karim',
      'lastName' => 'Benali',
      'status' => UserStatus::ACTIVE,
      'emailVerified' => true,
      'admin' => false,
    ],
    [
      'reference' => 'user-seed-external-auditor',
      'id' => '287c2f89-c67c-4c2b-8a40-36f46d6e5f5b',
      'username' => 'elena_rossi',
      'email' => 'elena.rossi@safecheck.example',
      'firstName' => 'Elena',
      'lastName' => 'Rossi',
      'status' => UserStatus::ACTIVE,
      'emailVerified' => true,
      'admin' => false,
    ],
    [
      'reference' => 'user-seed-warehouse-lead',
      'id' => 'fd5ca358-2664-4c16-a55e-91d7b13fca87',
      'username' => 'julien_mercier',
      'email' => 'julien.mercier@fireguard.local',
      'firstName' => 'Julien',
      'lastName' => 'Mercier',
      'status' => UserStatus::ACTIVE,
      'emailVerified' => true,
      'admin' => false,
    ],
    [
      'reference' => 'user-seed-departed-technician',
      'id' => '8e334c8d-ab1c-4929-8515-cc750abcc390',
      'username' => 'lucas_petit',
      'email' => 'lucas.petit@fireguard.local',
      'firstName' => 'Lucas',
      'lastName' => 'Petit',
      'status' => UserStatus::INACTIVE,
      'emailVerified' => true,
      'admin' => false,
    ],
    [
      'reference' => 'user-seed-new-joiner',
      'id' => '21434c1d-0e91-4c89-a3bf-8f67b2d61f9d',
      'username' => 'nadia_haddad',
      'email' => 'nadia.haddad@fireguard.local',
      'firstName' => 'Nadia',
      'lastName' => 'Haddad',
      'status' => UserStatus::PENDING_VERIFICATION,
      'emailVerified' => false,
      'admin' => false,
    ],
    [
      'reference' => 'user-seed-locked-account',
      'id' => '9b60e414-c5d3-437b-b394-44446a482e1c',
      'username' => 'victor_blanc',
      'email' => 'victor.blanc@fireguard.local',
      'firstName' => 'Victor',
      'lastName' => 'Blanc',
      'status' => UserStatus::LOCKED,
      'emailVerified' => true,
      'admin' => false,
    ],
  ];

  /**
   * Constant SECONDARY_ORG_OWNER_SEEDS.
   *
   * One account per secondary organization `OrganizationFixtures` seeds
   * alongside the main "Fireguard Seed Organization" — each the sole owner
   * of their own tenant, never a member of the main one. No `id` field: the
   * loop below derives it from `reference` via {@see SeedUuid}, and
   * `OrganizationFixtures` computes the exact same id the same way to set
   * `ownerUserId` without duplicating a literal.
   *
   * @since 1.3.0
   *
   * @var list<array{
   *   reference: string,
   *   username: string,
   *   email: string,
   *   firstName: string,
   *   lastName: string
   * }>
   */
  public const array SECONDARY_ORG_OWNER_SEEDS = [
    ['reference' => self::NOVA_OWNER_REFERENCE, 'username' => 'isabelle_fontaine', 'email' => 'isabelle.fontaine@novasecurite.example', 'firstName' => 'Isabelle', 'lastName' => 'Fontaine'],
    ['reference' => self::VIGILANCE_OWNER_REFERENCE, 'username' => 'pierre_lambert', 'email' => 'pierre.lambert@groupevigilance.example', 'firstName' => 'Pierre', 'lastName' => 'Lambert'],
    ['reference' => self::SAFEGUARD_OWNER_REFERENCE, 'username' => 'aicha_benyamina', 'email' => 'aicha.benyamina@safeguardconsulting.example', 'firstName' => 'Aïcha', 'lastName' => 'Benyamina'],
    ['reference' => self::PREVENTION_OWNER_REFERENCE, 'username' => 'olivier_chevalier', 'email' => 'olivier.chevalier@preventionalpha.example', 'firstName' => 'Olivier', 'lastName' => 'Chevalier'],
  ];

  /**
   * Constant BULK_STAFF_COUNT.
   *
   * On top of the 9 named {@see self::STAFF_SEEDS}, a plain generated pool so
   * the user directory — and the organization member roster it feeds via
   * `OrganizationFixtures::BULK_MEMBER_COUNT` — clears 50 rows. Below that
   * threshold an admin list renders a single page and the pagination controls
   * are never actually exercised.
   *
   * @since 1.2.0
   *
   * @var int
   */
  public const int BULK_STAFF_COUNT = 40;

  private const string ADMIN_USER_ID = 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890';

  /**
   * Constant BULK_FIRST_NAMES.
   *
   * @since 1.2.0
   *
   * @var list<string>
   */
  private const array BULK_FIRST_NAMES = ['Camille', 'Antoine', 'Léa', 'Hugo', 'Manon', 'Nicolas', 'Chloé', 'Maxime', 'Emma', 'Baptiste'];

  /**
   * Constant BULK_LAST_NAMES.
   *
   * @since 1.2.0
   *
   * @var list<string>
   */
  private const array BULK_LAST_NAMES = ['Dubois', 'Lefebvre', 'Simon', 'Laurent', 'Michel', 'Garcia', 'David', 'Bertrand', 'Roux', 'Vincent'];
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
    $this->assignAdminRole($manager, self::ADMIN_USER_ID);

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
    $this->assignUserRole($manager, 'b2c3d4e5-f6a7-4901-8cde-f23456789012');

    // Create Demo Users
    $demoUsers = [
      ['john_doe', 'john.doe@example.com', 'John', 'Doe'],
      ['jane_smith', 'jane.smith@example.com', 'Jane', 'Smith'],
      ['bob_wilson', 'bob.wilson@example.com', 'Bob', 'Wilson'],
    ];

    foreach ($demoUsers as $index => $userData) {
      $userId = SeedUuid::from(sprintf('user-demo:%d', $index));
      $user = $this->createUser(
        id: $userId,
        username: $userData[0],
        email: $userData[1],
        firstName: $userData[2],
        lastName: $userData[3],
        password: password_hash('Demo123!', PASSWORD_BCRYPT),
      );
      $record = $this->userMapper->toRecord($user);
      $manager->persist($record);
      $this->assignUserRole($manager, $userId);
    }

    // Create the demo workforce
    foreach (self::STAFF_SEEDS as $seed) {
      $staffUser = $this->createUser(
        id: $seed['id'],
        username: $seed['username'],
        email: $seed['email'],
        firstName: $seed['firstName'],
        lastName: $seed['lastName'],
        password: password_hash(self::STAFF_PASSWORD, PASSWORD_BCRYPT),
      );
      $staffRecord = $this->userMapper->toRecord($staffUser);
      $staffRecord->status = $seed['status']->value;
      $staffRecord->emailVerified = $seed['emailVerified'];
      $manager->persist($staffRecord);
      $this->addReference($seed['reference'], $staffRecord);

      if ($seed['admin']) {
        $this->assignAdminRole($manager, $seed['id']);
      }

      $this->assignUserRole($manager, $seed['id']);
    }

    // One owner account per secondary organization — see SECONDARY_ORG_OWNER_SEEDS.
    foreach (self::SECONDARY_ORG_OWNER_SEEDS as $seed) {
      $ownerId = SeedUuid::from($seed['reference']);
      $ownerUser = $this->createUser(
        id: $ownerId,
        username: $seed['username'],
        email: $seed['email'],
        firstName: $seed['firstName'],
        lastName: $seed['lastName'],
        password: password_hash(self::STAFF_PASSWORD, PASSWORD_BCRYPT),
      );
      $ownerRecord = $this->userMapper->toRecord($ownerUser);
      $ownerRecord->status = UserStatus::ACTIVE->value;
      $ownerRecord->emailVerified = true;
      $manager->persist($ownerRecord);
      $this->addReference($seed['reference'], $ownerRecord);
      $this->assignUserRole($manager, $ownerId);
    }

    // Bulk field staff — pads the roster past 50 rows purely for volume; see
    // BULK_STAFF_COUNT.
    for ($i = 0; $i < self::BULK_STAFF_COUNT; ++$i) {
      $bulkUser = $this->createUser(
        id: self::bulkStaffId($i),
        username: self::bulkStaffUsername($i),
        email: self::bulkStaffEmail($i),
        firstName: self::bulkStaffFirstName($i),
        lastName: self::bulkStaffLastName($i),
        password: password_hash(self::STAFF_PASSWORD, PASSWORD_BCRYPT),
      );
      $bulkRecord = $this->userMapper->toRecord($bulkUser);
      $bulkRecord->status = UserStatus::ACTIVE->value;
      $bulkRecord->emailVerified = true;
      $manager->persist($bulkRecord);
      $this->assignUserRole($manager, self::bulkStaffId($i));
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

  /**
   * Method bulkStaffId.
   *
   * Deterministic so {@see \Organization\Infrastructure\DataFixtures\OrganizationFixtures}
   * can compute the same id independently rather than depending on this
   * module's `Infrastructure` layer.
   *
   * @since 1.2.0
   *
   * @param int $index the bulk staff index, `0` to `BULK_STAFF_COUNT - 1`
   *
   * @return string the deterministic user id
   */
  public static function bulkStaffId(int $index): string
  {
    return SeedUuid::from(sprintf('user-bulk-staff:%d', $index));
  }

  /**
   * Method bulkStaffUsername.
   *
   * @since 1.2.0
   *
   * @param int $index the bulk staff index
   *
   * @return string the username
   */
  public static function bulkStaffUsername(int $index): string
  {
    return sprintf('field_staff_%02d', $index + 1);
  }

  /**
   * Method bulkStaffEmail.
   *
   * @since 1.2.0
   *
   * @param int $index the bulk staff index
   *
   * @return string the email address
   */
  public static function bulkStaffEmail(int $index): string
  {
    return sprintf('field.staff.%02d@fireguard.local', $index + 1);
  }

  /**
   * Method bulkStaffFirstName.
   *
   * @since 1.2.0
   *
   * @param int $index the bulk staff index
   *
   * @return string the first name
   */
  public static function bulkStaffFirstName(int $index): string
  {
    return self::BULK_FIRST_NAMES[$index % count(self::BULK_FIRST_NAMES)];
  }

  /**
   * Method bulkStaffLastName.
   *
   * Multiplied by a coprime-ish step so the (first, last) pairing does not
   * repeat every `count(BULK_FIRST_NAMES)` entries.
   *
   * @since 1.2.0
   *
   * @param int $index the bulk staff index
   *
   * @return string the last name
   */
  public static function bulkStaffLastName(int $index): string
  {
    return self::BULK_LAST_NAMES[($index * 3) % count(self::BULK_LAST_NAMES)];
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

  /**
   * Assigns the global "admin" role to a seeded user.
   *
   * @param ObjectManager $manager the object manager
   * @param string $userId the user ID to grant the role to
   */
  private function assignAdminRole(ObjectManager $manager, string $userId): void
  {
    /** @var RoleRecord $adminRole */
    $adminRole = $this->getReference(AuthorizationFixtures::ROLE_ADMIN, RoleRecord::class);

    $assignment = new RoleAssignmentRecord();
    $assignment->id = Uuid::v7()->toRfc4122();
    $assignment->role = $adminRole;
    $assignment->roleId = $adminRole->id;
    $assignment->subjectType = SubjectType::USER->value;
    $assignment->subjectId = $userId;
    $assignment->assignedAt = new DateTimeImmutable();

    $manager->persist($assignment);
  }

  /**
   * Assigns the default global "user" role to a seeded user.
   *
   * Fixture users release their domain events, so the runtime
   * AssignDefaultRoleOnUserCreatedHandler never fires for them; the baseline
   * self-service permissions (profile, sessions, OTP, trusted devices) must
   * therefore be granted explicitly here.
   *
   * @param ObjectManager $manager the object manager
   * @param string $userId the user ID to grant the role to
   */
  private function assignUserRole(ObjectManager $manager, string $userId): void
  {
    /** @var RoleRecord $userRole */
    $userRole = $this->getReference(AuthorizationFixtures::ROLE_USER, RoleRecord::class);

    $assignment = new RoleAssignmentRecord();
    $assignment->id = Uuid::v7()->toRfc4122();
    $assignment->role = $userRole;
    $assignment->roleId = $userRole->id;
    $assignment->subjectType = SubjectType::USER->value;
    $assignment->subjectId = $userId;
    $assignment->assignedAt = new DateTimeImmutable();

    $manager->persist($assignment);
  }
  // #endregion
}
