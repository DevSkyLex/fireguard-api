<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\Service;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Symfony\Adapter\Outbound\TranslatorAdapter;
use Tests\Support\Factory\EmailTranslatorTestFactory;
use Tests\Support\Shared\ProductConfigFixture;
use User\Application\Service\EmailChangeNotifier;

/**
 * Class EmailChangeNotifierProductTest
 *
 * @category Test
 */
#[CoversClass(EmailChangeNotifier::class)]
final class EmailChangeNotifierProductTest extends TestCase
{
  #[Test]
  public function usesTheConfiguredNameInPendingAndChangedNotificationBodies(): void
  {
    $captured = null;
    $notifications = $this->createStub(NotificationPort::class);
    $notifications->method('send')->willReturnCallback(static function (SendNotificationRequest $request) use (&$captured): SentNotification {
      $captured = $request;

      return new SentNotification('notification-id', $request->type, $request->subject, $request->body, [], [], [], new DateTimeImmutable());
    });
    $notifier = new EmailChangeNotifier(
      $notifications,
      'https://app.example.test',
      new TranslatorAdapter(EmailTranslatorTestFactory::create()),
      ProductConfigFixture::create(['product.public_name' => 'Workshop & service']),
    );

    $notifier->sendPendingNotice('old@example.test');

    self::assertInstanceOf(SendNotificationRequest::class, $captured);
    self::assertStringContainsString('your Workshop &amp; service account', $captured->body);
    self::assertStringNotContainsString('%product%', $captured->body);

    $notifier->sendChangedNotice('old@example.test');

    self::assertInstanceOf(SendNotificationRequest::class, $captured);
    self::assertStringContainsString('your Workshop &amp; service account', $captured->body);
    self::assertStringNotContainsString('%product%', $captured->body);
  }
}
