<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use Notification\Infrastructure\Persistence\Doctrine\Mapper\NotificationMapper;
use Notification\Infrastructure\Persistence\Doctrine\Record\NotificationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;

/**
 * Test NotificationMapperTest.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NotificationMapper::class)]
final class NotificationMapperTest extends TestCase
{
  // #region Constants
  private const string NOTIFICATION_ID = '550e8400-e29b-41d4-a716-446655440099';

  private const string RECIPIENT_USER_ID = 'user-1';

  private const string ORGANIZATION_ID = 'organization-1';
  // #endregion

  // #region Methods
  #[Test]
  public function testToRecordCopiesEveryField(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-05T10:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-06T10:00:00+00:00');
    $readAt = new DateTimeImmutable('2026-01-06T09:00:00+00:00');

    $notification = Notification::reconstitute(
      id: NotificationId::fromString(self::NOTIFICATION_ID),
      type: 'inspection.due',
      subject: 'Inspection due',
      body: 'An inspection is due next week.',
      channels: ['email', 'mercure'],
      payload: ['facilityId' => 'facility-1'],
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      recipientUserId: self::RECIPIENT_USER_ID,
      recipientEmail: new Email('recipient@example.com'),
      isRead: true,
      readAt: $readAt,
      organizationId: self::ORGANIZATION_ID,
    );

    $record = NotificationMapper::toRecord($notification);

    self::assertSame(self::NOTIFICATION_ID, $record->id);
    self::assertSame(self::RECIPIENT_USER_ID, $record->recipientUserId);
    self::assertSame('recipient@example.com', $record->recipientEmail);
    self::assertSame(self::ORGANIZATION_ID, $record->organizationId);
    self::assertSame('inspection.due', $record->type);
    self::assertSame('Inspection due', $record->subject);
    self::assertSame('An inspection is due next week.', $record->body);
    self::assertSame(['facilityId' => 'facility-1'], $record->payload);
    self::assertSame(['email', 'mercure'], $record->channels);
    self::assertTrue($record->isRead);
    self::assertSame($readAt, $record->readAt);
    self::assertSame($createdAt, $record->createdAt);
    self::assertSame($updatedAt, $record->updatedAt);
  }

  #[Test]
  public function testToRecordLeavesAnAbsentRecipientEmailNull(): void
  {
    $record = NotificationMapper::toRecord($this->notification());

    self::assertNull($record->recipientEmail);
    self::assertNull($record->organizationId);
    self::assertFalse($record->isRead);
    self::assertNull($record->readAt);
  }

  #[Test]
  public function testToDomainRebuildsTheAggregate(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-05T10:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-02-06T10:00:00+00:00');
    $readAt = new DateTimeImmutable('2026-02-06T09:00:00+00:00');

    $record = new NotificationRecord();
    $record->id = self::NOTIFICATION_ID;
    $record->recipientUserId = self::RECIPIENT_USER_ID;
    $record->recipientEmail = 'recipient@example.com';
    $record->organizationId = self::ORGANIZATION_ID;
    $record->type = 'equipment.overdue';
    $record->subject = 'Equipment overdue';
    $record->body = 'An extinguisher is overdue.';
    $record->payload = ['equipmentId' => 'equipment-1'];
    $record->channels = ['email'];
    $record->isRead = true;
    $record->readAt = $readAt;
    $record->createdAt = $createdAt;
    $record->updatedAt = $updatedAt;

    $notification = NotificationMapper::toDomain($record);

    self::assertSame(self::NOTIFICATION_ID, (string) $notification->id());
    self::assertSame(self::RECIPIENT_USER_ID, $notification->recipientUserId());
    self::assertInstanceOf(Email::class, $notification->recipientEmail());
    self::assertSame('recipient@example.com', (string) $notification->recipientEmail());
    self::assertSame(self::ORGANIZATION_ID, $notification->organizationId());
    self::assertSame('equipment.overdue', $notification->type());
    self::assertSame('Equipment overdue', $notification->subject());
    self::assertSame('An extinguisher is overdue.', $notification->body());
    self::assertSame(['equipmentId' => 'equipment-1'], $notification->payload());
    self::assertSame(['email'], $notification->channels());
    self::assertTrue($notification->isRead());
    self::assertSame($readAt, $notification->readAt());
    self::assertSame($createdAt, $notification->createdAt());
    self::assertSame($updatedAt, $notification->updatedAt());
  }

  #[Test]
  public function testToDomainLeavesAnAbsentRecipientEmailNull(): void
  {
    $timestamp = new DateTimeImmutable('2026-02-05T10:00:00+00:00');

    $record = new NotificationRecord();
    $record->id = self::NOTIFICATION_ID;
    $record->recipientEmail = null;
    $record->type = 'generic';
    $record->subject = 'Subject';
    $record->body = 'Body';
    $record->createdAt = $timestamp;
    $record->updatedAt = $timestamp;

    $notification = NotificationMapper::toDomain($record);

    self::assertNull($notification->recipientEmail());
    self::assertNull($notification->recipientUserId());
    self::assertNull($notification->organizationId());
    self::assertFalse($notification->isRead());
    self::assertSame([], $notification->channels());
    self::assertSame([], $notification->payload());
  }

  #[Test]
  public function testRoundTripPreservesState(): void
  {
    $roundTripped = NotificationMapper::toRecord(
      NotificationMapper::toDomain(NotificationMapper::toRecord($this->notification())),
    );

    self::assertSame(self::NOTIFICATION_ID, $roundTripped->id);
    self::assertSame('inspection.due', $roundTripped->type);
    self::assertSame(['mercure'], $roundTripped->channels);
    self::assertNull($roundTripped->recipientEmail);
  }
  // #endregion

  // #region Helpers
  private function notification(): Notification
  {
    $timestamp = new DateTimeImmutable('2026-03-05T10:00:00+00:00');

    return Notification::reconstitute(
      id: NotificationId::fromString(self::NOTIFICATION_ID),
      type: 'inspection.due',
      subject: 'Inspection due',
      body: 'An inspection is due next week.',
      channels: ['mercure'],
      payload: [],
      createdAt: $timestamp,
      updatedAt: $timestamp,
      recipientUserId: self::RECIPIENT_USER_ID,
    );
  }
  // #endregion
}
