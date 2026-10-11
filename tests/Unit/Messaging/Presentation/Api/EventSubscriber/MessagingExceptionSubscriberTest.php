<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\EventSubscriber;

use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingAttachmentNotFoundException, MessagingRevisionPreconditionException};
use Messaging\Presentation\Api\EventSubscriber\MessagingExceptionSubscriber;
use Messaging\Presentation\Api\Service\MessagingExceptionMapper;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

/**
 * Class MessagingExceptionSubscriberTest
 *
 * Pins the central status mapping of bus-wrapped denial and concurrency failures.
 *
 * @category EventSubscriber Tests
 */
#[CoversClass(MessagingExceptionSubscriber::class)]
final class MessagingExceptionSubscriberTest extends TestCase
{
  /**
   * @return iterable<string, array{Throwable, int}>
   */
  public static function failures(): iterable
  {
    yield 'permission' => [new MessagingAccessDeniedException('Denied'), 403];
    yield 'missing attachment' => [MessagingAttachmentNotFoundException::withId('missing'), 404];
    yield 'missing precondition' => [new MessagingRevisionPreconditionException(true), 428];
    yield 'stale precondition' => [new MessagingRevisionPreconditionException(false), 412];
  }

  #[Test]
  #[DataProvider('failures')]
  public function mapsBusWrappedFailures(Throwable $failure, int $status): void
  {
    $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/api/messaging-attachments/id'), HttpKernelInterface::MAIN_REQUEST, new RuntimeException('Bus failed', previous: $failure));
    new MessagingExceptionSubscriber(new MessagingExceptionMapper())->onException($event);
    self::assertInstanceOf(HttpExceptionInterface::class, $event->getThrowable());
    self::assertSame($status, $event->getThrowable()->getStatusCode());
  }

  #[Test]
  public function preservesFailuresOutsideMessagingRoutes(): void
  {
    $failure = new RuntimeException('Other module failure');
    $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/api/equipment/id'), HttpKernelInterface::MAIN_REQUEST, $failure);
    new MessagingExceptionSubscriber(new MessagingExceptionMapper())->onException($event);
    self::assertSame($failure, $event->getThrowable());
  }
}
