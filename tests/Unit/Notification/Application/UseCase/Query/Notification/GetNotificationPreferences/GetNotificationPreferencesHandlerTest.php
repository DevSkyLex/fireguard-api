<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\UseCase\Query\Notification\GetNotificationPreferences;

use DateTimeImmutable;
use Notification\Application\Port\Outbound\NotificationPreferenceRepositoryPort;
use Notification\Application\UseCase\Query\Notification\GetNotificationPreferences\{
  GetNotificationPreferencesHandler,
  GetNotificationPreferencesQuery,
  GetNotificationPreferencesResult
};
use Notification\Domain\Model\NotificationPreference\NotificationPreference;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test GetNotificationPreferencesHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetNotificationPreferencesHandler::class)]
final class GetNotificationPreferencesHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsEmptyListWhenTheUserHasNoCustomization(): void
  {
    /** @var NotificationPreferenceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationPreferenceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->with('550e8400-e29b-41d4-a716-446655443000')
      ->willReturn([]);

    $handler = new GetNotificationPreferencesHandler(preferenceRepository: $repository);

    $result = $handler->__invoke(new GetNotificationPreferencesQuery(userId: '550e8400-e29b-41d4-a716-446655443000'));

    self::assertInstanceOf(GetNotificationPreferencesResult::class, $result);
    self::assertSame([], $result->preferences);
  }

  #[Test]
  public function testInvokeMapsEveryCustomizedPreference(): void
  {
    $updatedAt = new DateTimeImmutable('2026-07-18T10:00:00+00:00');

    /** @var NotificationPreferenceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationPreferenceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->with('550e8400-e29b-41d4-a716-446655443001')
      ->willReturn([
        NotificationPreference::reconstitute(
          userId: '550e8400-e29b-41d4-a716-446655443001',
          category: 'organization',
          emailEnabled: false,
          mercureEnabled: true,
          updatedAt: $updatedAt,
        ),
        NotificationPreference::reconstitute(
          userId: '550e8400-e29b-41d4-a716-446655443001',
          category: 'system',
          emailEnabled: false,
          mercureEnabled: false,
          updatedAt: $updatedAt,
        ),
      ]);

    $handler = new GetNotificationPreferencesHandler(preferenceRepository: $repository);

    $result = $handler->__invoke(new GetNotificationPreferencesQuery(userId: '550e8400-e29b-41d4-a716-446655443001'));

    self::assertCount(2, $result->preferences);
    self::assertSame('organization', $result->preferences[0]->category);
    self::assertFalse($result->preferences[0]->emailEnabled);
    self::assertTrue($result->preferences[0]->mercureEnabled);
    self::assertSame($updatedAt, $result->preferences[0]->updatedAt);
    self::assertSame('system', $result->preferences[1]->category);
    self::assertFalse($result->preferences[1]->emailEnabled);
    self::assertFalse($result->preferences[1]->mercureEnabled);
  }
}
