<?php

declare(strict_types=1);

namespace Tests\Integration\Notification\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\{NotificationId, NotificationType};
use Notification\Infrastructure\Persistence\Doctrine\Repository\NotificationRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

#[CoversClass(NotificationRepository::class)]
final class NotificationRepositoryIntegrationTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  private NotificationRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->repository = new NotificationRepository(entityManager: $this->entityManager);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testFindByUserIdMasksOldReadNotificationsForConfiguredCategories(): void
  {
    $this->repository->save($this->createNotification(
      id: '550e8400-e29b-41d4-a716-446655442310',
      type: NotificationType::USER_EMAIL_VERIFIED,
      subject: 'Email verified',
      body: 'Old read user notification',
      userId: '550e8400-e29b-41d4-a716-446655442399',
      createdAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-20T10:00:00+00:00'),
      isRead: true,
      readAt: new DateTimeImmutable('2026-01-20T10:00:00+00:00'),
    ));

    $this->repository->save($this->createNotification(
      id: '550e8400-e29b-41d4-a716-446655442311',
      type: NotificationType::ORGANIZATION_MEMBER_REMOVED,
      subject: 'Access removed',
      body: 'Old read organization notification',
      userId: '550e8400-e29b-41d4-a716-446655442399',
      createdAt: new DateTimeImmutable('2026-01-18T10:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-21T10:00:00+00:00'),
      isRead: true,
      readAt: new DateTimeImmutable('2026-01-21T10:00:00+00:00'),
    ));

    $this->repository->save($this->createNotification(
      id: '550e8400-e29b-41d4-a716-446655442312',
      type: NotificationType::FACILITY_ARCHIVED,
      subject: 'Facility archived',
      body: 'Recent read facility notification',
      userId: '550e8400-e29b-41d4-a716-446655442399',
      createdAt: new DateTimeImmutable('2026-03-20T10:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-21T10:00:00+00:00'),
      isRead: true,
      readAt: new DateTimeImmutable('2026-03-21T10:00:00+00:00'),
    ));

    $this->repository->save($this->createNotification(
      id: '550e8400-e29b-41d4-a716-446655442313',
      type: NotificationType::EQUIPMENT_UNDER_MAINTENANCE,
      subject: 'Equipment under maintenance',
      body: 'Unread equipment notification',
      userId: '550e8400-e29b-41d4-a716-446655442399',
      createdAt: new DateTimeImmutable('2026-03-25T10:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-25T10:00:00+00:00'),
      isRead: false,
      readAt: null,
    ));

    $this->repository->save($this->createNotification(
      id: '550e8400-e29b-41d4-a716-446655442314',
      type: NotificationType::USER_EMAIL_VERIFIED,
      subject: 'Email verified',
      body: 'Other user notification',
      userId: '550e8400-e29b-41d4-a716-446655442400',
      createdAt: new DateTimeImmutable('2026-03-26T10:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-27T10:00:00+00:00'),
      isRead: false,
      readAt: null,
    ));

    $notifications = $this->repository->findByUserId(
      userId: '550e8400-e29b-41d4-a716-446655442399',
      limit: 10,
      hideReadBefore: new DateTimeImmutable('2026-03-02T00:00:00+00:00'),
      hiddenReadCategories: [
        NotificationType::CATEGORY_USER,
        NotificationType::CATEGORY_FACILITY,
        NotificationType::CATEGORY_EQUIPMENT,
      ],
    );

    self::assertCount(3, $notifications);
    self::assertSame([
      NotificationType::EQUIPMENT_UNDER_MAINTENANCE,
      NotificationType::FACILITY_ARCHIVED,
      NotificationType::ORGANIZATION_MEMBER_REMOVED,
    ], array_map(
      static fn (Notification $notification): string => $notification->type(),
      $notifications,
    ));
  }

  #[Test]
  public function testFindByUserIdHonoursTheBeforeCursorForStableInboxPagination(): void
  {
    // Exercises the `before` cursor added for the unified inbox seam
    // (NotificationInboxSourceProviderAdapter): a mocked QueryBuilder never
    // parses the `n.createdAt < :before` DQL condition, so only a real
    // connection catches a broken cursor filter.
    $this->repository->save($this->createNotification(
      id: '550e8400-e29b-41d4-a716-446655442320',
      type: NotificationType::ORGANIZATION_MEMBER_REMOVED,
      subject: 'Oldest',
      body: 'Oldest notification',
      userId: '550e8400-e29b-41d4-a716-446655442398',
      createdAt: new DateTimeImmutable('2026-07-18T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-07-18T08:00:00+00:00'),
      isRead: false,
      readAt: null,
    ));

    $this->repository->save($this->createNotification(
      id: '550e8400-e29b-41d4-a716-446655442321',
      type: NotificationType::ORGANIZATION_MEMBER_REMOVED,
      subject: 'Middle',
      body: 'Middle notification',
      userId: '550e8400-e29b-41d4-a716-446655442398',
      createdAt: new DateTimeImmutable('2026-07-18T09:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-07-18T09:00:00+00:00'),
      isRead: false,
      readAt: null,
    ));

    $this->repository->save($this->createNotification(
      id: '550e8400-e29b-41d4-a716-446655442322',
      type: NotificationType::ORGANIZATION_MEMBER_REMOVED,
      subject: 'Newest',
      body: 'Newest notification',
      userId: '550e8400-e29b-41d4-a716-446655442398',
      createdAt: new DateTimeImmutable('2026-07-18T10:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-07-18T10:00:00+00:00'),
      isRead: false,
      readAt: null,
    ));

    $notifications = $this->repository->findByUserId(
      userId: '550e8400-e29b-41d4-a716-446655442398',
      limit: 10,
      before: new DateTimeImmutable('2026-07-18T10:00:00+00:00'),
    );

    // Strictly BEFORE the cursor: excludes "Newest" (== cursor), keeps the
    // two older rows, still ordered most-recent-first.
    self::assertSame(['Middle', 'Oldest'], array_map(
      static fn (Notification $notification): string => $notification->subject(),
      $notifications,
    ));
  }

  private function createNotification(
    string $id,
    string $type,
    string $subject,
    string $body,
    string $userId,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    bool $isRead,
    ?DateTimeImmutable $readAt,
  ): Notification {
    return Notification::reconstitute(
      id: NotificationId::fromString($id),
      type: $type,
      subject: $subject,
      body: $body,
      channels: ['mercure'],
      payload: [],
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      recipientUserId: $userId,
      recipientEmail: new Email('user@example.com'),
      isRead: $isRead,
      readAt: $readAt,
    );
  }
}
