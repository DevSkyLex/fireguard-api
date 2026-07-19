<?php

declare(strict_types=1);

namespace Tests\Integration\Notification\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Notification\Domain\Model\NotificationPreference\NotificationPreference;
use Notification\Infrastructure\Persistence\Doctrine\Repository\NotificationPreferenceRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test NotificationPreferenceRepositoryIntegrationTest.
 *
 * Exercises the composite-key (`userId`, `category`) persistence semantics
 * against a real database connection, since a mocked QueryBuilder/Doctrine
 * repository would not catch a broken composite `find()`/`persist()` shape.
 *
 * @category Integration Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NotificationPreferenceRepository::class)]
final class NotificationPreferenceRepositoryIntegrationTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  private NotificationPreferenceRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->repository = new NotificationPreferenceRepository(entityManager: $this->entityManager);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testFindByUserIdAndCategoryReturnsNullWhenNoRowExists(): void
  {
    self::assertNull($this->repository->findByUserIdAndCategory(
      '550e8400-e29b-41d4-a716-446655444001',
      'organization',
    ));
  }

  #[Test]
  public function testSaveManyInsertsThenUpdatesTheSameCompositeKey(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655444002';

    $this->repository->saveMany([
      NotificationPreference::create(userId: $userId, category: 'organization', emailEnabled: false, mercureEnabled: true),
      NotificationPreference::create(userId: $userId, category: 'system', emailEnabled: true, mercureEnabled: false),
    ]);

    $organization = $this->repository->findByUserIdAndCategory($userId, 'organization');
    self::assertInstanceOf(NotificationPreference::class, $organization);
    self::assertFalse($organization->isEmailEnabled());
    self::assertTrue($organization->isMercureEnabled());

    $all = $this->repository->findByUserId($userId);
    self::assertCount(2, $all);
    self::assertSame(['organization', 'system'], array_map(
      static fn (NotificationPreference $preference): string => $preference->category(),
      $all,
    ));

    // Re-saving the same (userId, category) pair must update in place, not
    // insert a duplicate row (the table's PK is composite on both columns).
    $this->repository->saveMany([
      NotificationPreference::create(userId: $userId, category: 'organization', emailEnabled: true, mercureEnabled: true),
    ]);

    $updated = $this->repository->findByUserIdAndCategory($userId, 'organization');
    self::assertInstanceOf(NotificationPreference::class, $updated);
    self::assertTrue($updated->isEmailEnabled());

    $allAfterUpdate = $this->repository->findByUserId($userId);
    self::assertCount(2, $allAfterUpdate, 'the upsert must not have created a duplicate row');
  }

  #[Test]
  public function testFindByUserIdOnlyReturnsThatUsersRows(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655444003';
    $otherUserId = '550e8400-e29b-41d4-a716-446655444004';

    $this->repository->saveMany([
      NotificationPreference::create(userId: $userId, category: 'organization'),
      NotificationPreference::create(userId: $otherUserId, category: 'organization'),
    ]);

    $found = $this->repository->findByUserId($userId);

    self::assertCount(1, $found);
    self::assertSame($userId, $found[0]->userId());
  }
}
