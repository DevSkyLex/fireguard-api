<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\UseCase\Command\Notification\UpdateNotificationPreferences;

use DateTimeImmutable;
use Notification\Application\Port\Outbound\NotificationPreferenceRepositoryPort;
use Notification\Application\UseCase\Command\Notification\UpdateNotificationPreferences\{
  UpdateNotificationPreferencesCommand,
  UpdateNotificationPreferencesHandler,
  UpdateNotificationPreferencesResult
};
use Notification\Domain\Model\NotificationPreference\NotificationPreference;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * Test UpdateNotificationPreferencesHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateNotificationPreferencesHandler::class)]
final class UpdateNotificationPreferencesHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeUpsertsEveryEntryAndReturnsTheFullCustomizedSet(): void
  {
    $updatedAt = new DateTimeImmutable('2026-07-18T12:00:00+00:00');

    /** @var NotificationPreferenceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationPreferenceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('saveMany')
      ->with(self::callback(static function (array $preferences): bool {
        if (1 !== count($preferences)) {
          return false;
        }

        /** @var NotificationPreference $preference */
        $preference = $preferences[0];

        return '550e8400-e29b-41d4-a716-446655443100' === $preference->userId()
          && 'organization' === $preference->category()
          && false === $preference->isEmailEnabled()
          && true === $preference->isMercureEnabled();
      }));

    $repository->expects(self::once())
      ->method('findByUserId')
      ->with('550e8400-e29b-41d4-a716-446655443100')
      ->willReturn([
        NotificationPreference::reconstitute(
          userId: '550e8400-e29b-41d4-a716-446655443100',
          category: 'organization',
          emailEnabled: false,
          mercureEnabled: true,
          updatedAt: $updatedAt,
        ),
      ]);

    $handler = new UpdateNotificationPreferencesHandler(preferenceRepository: $repository);

    $result = $handler->__invoke(new UpdateNotificationPreferencesCommand(
      userId: '550e8400-e29b-41d4-a716-446655443100',
      preferences: [
        ['category' => 'organization', 'emailEnabled' => false, 'mercureEnabled' => true],
      ],
    ));

    self::assertInstanceOf(UpdateNotificationPreferencesResult::class, $result);
    self::assertCount(1, $result->preferences);
    self::assertSame('organization', $result->preferences[0]->category);
    self::assertFalse($result->preferences[0]->emailEnabled);
    self::assertTrue($result->preferences[0]->mercureEnabled);
  }

  #[Test]
  public function testInvokeDeduplicatesDuplicateCategoriesKeepingTheLastEntry(): void
  {
    /** @var NotificationPreferenceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationPreferenceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('saveMany')
      ->with(self::callback(static function (array $preferences): bool {
        if (1 !== count($preferences)) {
          return false;
        }

        /** @var NotificationPreference $preference */
        $preference = $preferences[0];

        // The second entry ("last one wins") disabled mercure.
        return false === $preference->isMercureEnabled();
      }));

    $repository->method('findByUserId')->willReturn([]);

    $handler = new UpdateNotificationPreferencesHandler(preferenceRepository: $repository);

    $handler->__invoke(new UpdateNotificationPreferencesCommand(
      userId: '550e8400-e29b-41d4-a716-446655443101',
      preferences: [
        ['category' => 'organization', 'emailEnabled' => true, 'mercureEnabled' => true],
        ['category' => 'organization', 'emailEnabled' => true, 'mercureEnabled' => false],
      ],
    ));
  }
}
