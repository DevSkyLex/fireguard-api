<?php

declare(strict_types=1);

namespace User\Infrastructure\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Shared\Domain\ValueObject\Email;
use Shared\Domain\ValueObject\TenantId;
use Shared\Infrastructure\Service\UuidEventIdProvider;
use Shared\Infrastructure\Symfony\Adapter\Outbound\UuidGeneratorAdapter;
use User\Domain\Model\User;
use User\Domain\ValueObject\HashedPassword;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\Username;
use User\Domain\ValueObject\UserProfile;
use User\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;

use function password_hash;
use function sprintf;

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
class UserFixtures extends Fixture implements FixtureGroupInterface
{
    // #region Constants
    public const string ADMIN_USER_REFERENCE = 'admin-user';
    public const string TEST_USER_REFERENCE = 'test-user';
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
        return ['user', 'default'];
    }

    public function load(ObjectManager $manager): void
    {
        // Create Admin User
        $adminUser = $this->createUser(
            id: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
            username: 'admin',
            email: 'admin@fireguard.local',
            firstName: 'Admin',
            lastName: 'User',
            password: password_hash('Admin123!', PASSWORD_BCRYPT)
        );
        $adminRecord = $this->userMapper->toRecord($adminUser);
        $manager->persist($adminRecord);
        $this->addReference(self::ADMIN_USER_REFERENCE, $adminRecord);

        // Create Test User
        $testUser = $this->createUser(
            id: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
            username: 'testuser',
            email: 'test@fireguard.local',
            firstName: 'Test',
            lastName: 'User',
            password: password_hash('Test123!', PASSWORD_BCRYPT)
        );
        $testRecord = $this->userMapper->toRecord($testUser);
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
                password: password_hash('Demo123!', PASSWORD_BCRYPT)
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
            password: password_hash('DevClient123!', PASSWORD_BCRYPT)
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
                lastName: $lastName
            ),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            eventIdProvider: new UuidEventIdProvider(new UuidGeneratorAdapter()),
        );

        // Clear domain events to avoid handler issues during fixtures
        $user->releaseEvents();

        return $user;
    }
    // #endregion
}
