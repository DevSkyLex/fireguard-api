<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\EventListener;

use Otp\Domain\Event\OtpGeneratedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shared\Domain\ValueObject\Uuid;
use Shared\Infrastructure\EventListener\AuditEventListener;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

use function is_string;

/**
 * Test AuditEventListenerTest.
 *
 * @category Event Listener Tests
 */
#[CoversClass(className: AuditEventListener::class)]
final class AuditEventListenerTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testGetSubscribedEvents(): void
  {
    self::assertSame([
      WorkerMessageHandledEvent::class => 'onMessageHandled',
    ], AuditEventListener::getSubscribedEvents());
  }

  #[Test]
  public function testOnMessageHandledLogsDomainEvent(): void
  {
    $event = new OtpGeneratedEvent(
      otpId: 'otp-123',
      userId: 'user-123',
      purpose: 'login',
      channel: 'email',
    );

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('info')
      ->with(
        'Domain event processed',
        self::callback(function (array $context): bool {
          return ($context['event_id'] ?? null) instanceof Uuid
            && 'OtpGeneratedEvent' === ($context['event_type'] ?? null)
            && is_string($context['occurred_at'] ?? null)
            && is_string($context['event_class'] ?? null);
        }),
      );

    $listener = new AuditEventListener($logger);

    $messageEvent = new WorkerMessageHandledEvent(
      new Envelope($event),
      'async',
    );

    $listener->onMessageHandled($messageEvent);
  }

  #[Test]
  public function testOnMessageHandledIgnoresNonDomainEvent(): void
  {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::never())
      ->method('info');

    $listener = new AuditEventListener($logger);

    $messageEvent = new WorkerMessageHandledEvent(
      new Envelope(new stdClass()),
      'async',
    );

    $listener->onMessageHandled($messageEvent);
  }
  // #endregion
}
