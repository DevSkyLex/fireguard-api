<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\EventSubscriber;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Domain\Attachment\{AttachmentConstraints, InvalidAttachmentException};
use Shared\Presentation\Api\EventSubscriber\AttachmentConstraintExceptionSubscriber;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\{HttpKernelInterface, KernelEvents};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Test AttachmentConstraintExceptionSubscriberTest.
 *
 * @category EventSubscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AttachmentConstraintExceptionSubscriber::class)]
final class AttachmentConstraintExceptionSubscriberTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testSubscribesToKernelExceptionBeforeApiPlatform(): void
  {
    $events = AttachmentConstraintExceptionSubscriber::getSubscribedEvents();

    self::assertArrayHasKey(KernelEvents::EXCEPTION, $events);
    self::assertSame(['onKernelException', 10], $events[KernelEvents::EXCEPTION]);
  }

  #[Test]
  public function testMapsABusWrappedCountViolationToUnprocessableEntity(): void
  {
    $domain = InvalidAttachmentException::forCount(
      AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT,
      AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT,
    );
    $wrapped = MessengerRuntimeException::wrap(
      new HandlerFailedException(new Envelope(new stdClass()), [$domain]),
    );

    $event = $this->createEvent($wrapped);

    new AttachmentConstraintExceptionSubscriber()->onKernelException($event);

    $throwable = $event->getThrowable();
    self::assertInstanceOf(UnprocessableEntityHttpException::class, $throwable);
    self::assertSame(422, $throwable->getStatusCode());
    self::assertSame($domain->getMessage(), $throwable->getMessage());
    self::assertSame($wrapped, $throwable->getPrevious());
  }

  #[Test]
  public function testMapsABareDomainExceptionToo(): void
  {
    $domain = InvalidAttachmentException::forMimeType('image/svg+xml');

    $event = $this->createEvent($domain);

    new AttachmentConstraintExceptionSubscriber()->onKernelException($event);

    self::assertInstanceOf(UnprocessableEntityHttpException::class, $event->getThrowable());
  }

  #[Test]
  public function testLeavesAnUnrelatedExceptionUntouched(): void
  {
    $unrelated = MessengerRuntimeException::wrap(new RuntimeException('Database error.'));

    $event = $this->createEvent($unrelated);

    new AttachmentConstraintExceptionSubscriber()->onKernelException($event);

    self::assertSame($unrelated, $event->getThrowable());
  }

  #[Test]
  public function testLeavesAnAlreadyMappedHttpExceptionUntouched(): void
  {
    // The multipart guard's own 422 carries the domain exception as previous;
    // rewrapping it would discard the guard's message for a duplicate.
    $mapped = new UnprocessableEntityHttpException(
      'MIME type "image/svg+xml" is not allowed for attachments.',
      InvalidAttachmentException::forMimeType('image/svg+xml'),
    );

    $event = $this->createEvent($mapped);

    new AttachmentConstraintExceptionSubscriber()->onKernelException($event);

    self::assertSame($mapped, $event->getThrowable());
  }
  // #endregion

  // #region Helpers
  /**
   * Method createEvent.
   *
   * Builds a main-request kernel exception event carrying the throwable.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the throwable under test
   *
   * @return ExceptionEvent the event
   */
  private function createEvent(Throwable $exception): ExceptionEvent
  {
    return new ExceptionEvent(
      kernel: $this->createStub(HttpKernelInterface::class),
      request: new Request(),
      requestType: HttpKernelInterface::MAIN_REQUEST,
      e: $exception,
    );
  }
  // #endregion
}
