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

  #[Test]
  public function testSaveUpdatesTheExistingRowInPlaceAndFindByIdForUserScopesToTheRecipient(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655442380';
    $id = '550e8400-e29b-41d4-a716-446655442330';

    $this->repository->save($this->createNotification(
      id: $id,
      type: NotificationType::ORGANIZATION_MEMBER_ADDED,
      subject: 'Before',
      body: 'Before body',
      userId: $userId,
      createdAt: new DateTimeImmutable('2026-05-01T10:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-05-01T10:00:00+00:00'),
      isRead: false,
      readAt: null,
    ));

    // Same identifier: the second save must take the "existing record" branch
    // and mutate every column instead of inserting a duplicate row.
    $this->repository->save($this->createNotification(
      id: $id,
      type: NotificationType::ORGANIZATION_MEMBER_REMOVED,
      subject: 'After',
      body: 'After body',
      userId: $userId,
      createdAt: new DateTimeImmutable('2026-05-01T10:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-05-02T10:00:00+00:00'),
      isRead: true,
      readAt: new DateTimeImmutable('2026-05-02T10:00:00+00:00'),
      organizationId: '550e8400-e29b-41d4-a716-4466554423a1',
    ));

    $found = $this->repository->findByIdForUser(NotificationId::fromString($id), $userId);

    self::assertNotNull($found);
    self::assertSame('After', $found->subject());
    self::assertSame('After body', $found->body());
    self::assertSame(NotificationType::ORGANIZATION_MEMBER_REMOVED, $found->type());
    self::assertTrue($found->isRead());
    self::assertSame(1, $this->repository->countByUserId($userId));

    // Scoped to the recipient, and null for an unknown identifier.
    self::assertNull($this->repository->findByIdForUser(
      NotificationId::fromString($id),
      '550e8400-e29b-41d4-a716-446655442381',
    ));
    self::assertNull($this->repository->findByIdForUser(
      NotificationId::fromString('550e8400-e29b-41d4-a716-4466554423ff'),
      $userId,
    ));
  }

  #[Test]
  public function testCountersApplyTheSameFiltersAsTheListing(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655442390';
    $otherUserId = '550e8400-e29b-41d4-a716-446655442391';
    $organizationA = '550e8400-e29b-41d4-a716-4466554423b1';
    $organizationB = '550e8400-e29b-41d4-a716-4466554423b2';

    $this->repository->save($this->createNotification(
      id: '550e8400-e29b-41d4-a716-446655442340',
      type: NotificationType::USER_EMAIL_VERIFIED,
      subject: 'User unread',
      body: 'User unread body',
      userId: $userId,
      createdAt: new DateTimeImmutable('2026-06-01T10:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-06-01T10:00:00+00:00'),
      isRead: false,
      readAt: null,
      organizationId: $organizationA,
    ));

    $this->repository->save($this->createNotification(
      id: '550e8400-e29b-41d4-a716-446655442341',
      type: NotificationType::ORGANIZATION_MEMBER_REMOVED,
      subject: 'Organization read',
      body: 'Organization read body',
      userId: $userId,
      createdAt: new DateTimeImmutable('2026-06-02T10:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-06-03T10:00:00+00:00'),
      isRead: true,
      readAt: new DateTimeImmutable('2026-06-03T10:00:00+00:00'),
      organizationId: $organizationA,
    ));

    $this->repository->save($this->createNotification(
      id: '550e8400-e29b-41d4-a716-446655442342',
      type: NotificationType::ORGANIZATION_MEMBER_ADDED,
      subject: 'Organization unread',
      body: 'Organization unread body',
      userId: $userId,
      createdAt: new DateTimeImmutable('2026-06-04T10:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-06-04T10:00:00+00:00'),
      isRead: false,
      readAt: null,
      organizationId: $organizationB,
    ));

    $this->repository->save($this->createNotification(
      id: '550e8400-e29b-41d4-a716-446655442343',
      type: NotificationType::USER_EMAIL_VERIFIED,
      subject: 'Someone else',
      body: 'Someone else body',
      userId: $otherUserId,
      createdAt: new DateTimeImmutable('2026-06-05T10:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-06-05T10:00:00+00:00'),
      isRead: false,
      readAt: null,
      organizationId: $organizationA,
    ));

    self::assertSame(3, $this->repository->countByUserId($userId));
    self::assertSame(2, $this->repository->countByUserId($userId, onlyUnread: true));
    self::assertSame(1, $this->repository->countByUserId($userId, type: NotificationType::USER_EMAIL_VERIFIED));
    self::assertSame(2, $this->repository->countByUserId($userId, category: NotificationType::CATEGORY_ORGANIZATION));
    self::assertSame(2, $this->repository->countByUserId($userId, organizationId: $organizationA));
    self::assertSame(2, $this->repository->countUnreadByUserId($userId));
    self::assertSame(1, $this->repository->countUnreadByUserId($userId, $organizationA));
    self::assertSame(1, $this->repository->countUnreadByUserId($userId, $organizationB));

    // A type filter wins over the category filter when both are supplied.
    self::assertSame(1, $this->repository->countByUserId(
      $userId,
      type: NotificationType::ORGANIZATION_MEMBER_ADDED,
      category: NotificationType::CATEGORY_USER,
    ));

    // onlyUnread short-circuits the read-history masking branch entirely.
    self::assertSame(2, $this->repository->countByUserId(
      $userId,
      onlyUnread: true,
      hideReadBefore: new DateTimeImmutable('2026-06-10T00:00:00+00:00'),
      hiddenReadCategories: [NotificationType::CATEGORY_ORGANIZATION],
    ));

    $unreadOnly = $this->repository->findByUserId($userId, onlyUnread: true, limit: 10);

    self::assertSame(['Organization unread', 'User unread'], array_map(
      static fn (Notification $notification): string => $notification->subject(),
      $unreadOnly,
    ));
  }

  #[Test]
  public function testMarkAllAsReadForUserScopesToTheUserAndOptionalOrganization(): void
  {
    $userId = '550e8400-e29b-41d4-a716-4466554423c0';
    $otherUserId = '550e8400-e29b-41d4-a716-4466554423c1';
    $organizationA = '550e8400-e29b-41d4-a716-4466554423d1';
    $organizationB = '550e8400-e29b-41d4-a716-4466554423d2';

    foreach ([
      ['550e8400-e29b-41d4-a716-446655442350', $userId, $organizationA],
      ['550e8400-e29b-41d4-a716-446655442351', $userId, $organizationA],
      ['550e8400-e29b-41d4-a716-446655442352', $userId, $organizationB],
      ['550e8400-e29b-41d4-a716-446655442353', $otherUserId, $organizationA],
    ] as [$notificationId, $recipientId, $organizationId]) {
      $this->repository->save($this->createNotification(
        id: $notificationId,
        type: NotificationType::ORGANIZATION_MEMBER_ADDED,
        subject: 'Unread ' . $notificationId,
        body: 'Unread body',
        userId: $recipientId,
        createdAt: new DateTimeImmutable('2026-06-20T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-06-20T10:00:00+00:00'),
        isRead: false,
        readAt: null,
        organizationId: $organizationId,
      ));
    }

    $scoped = $this->repository->markAllAsReadForUser(
      $userId,
      $organizationA,
      new DateTimeImmutable('2026-06-21T10:00:00+00:00'),
    );

    self::assertSame(2, $scoped);
    self::assertSame(1, $this->repository->countUnreadByUserId($userId));

    // No organization scope and no explicit instant: falls back to "now".
    $remaining = $this->repository->markAllAsReadForUser($userId);

    self::assertSame(1, $remaining);
    self::assertSame(0, $this->repository->countUnreadByUserId($userId));

    // Already-read rows are skipped, and other recipients are untouched.
    self::assertSame(0, $this->repository->markAllAsReadForUser($userId));
    self::assertSame(1, $this->repository->countUnreadByUserId($otherUserId));
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
    ?string $organizationId = null,
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
      organizationId: $organizationId,
    );
  }
}
