<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\EventSubscriber;

use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Shared\Application\Contract\Storage\FileCleanupRequested;
use Shared\Application\Port\Outbound\{FileStoragePort, IdempotentConsumerPort};
use Shared\Infrastructure\EventSubscriber\FileCleanupSubscriber;
use Shared\Infrastructure\Messaging\Outbox\DurableEventContext;

final class FileCleanupSubscriberTest extends TestCase
{
  public function testRejectsSynchronousDeliveryBeforeTouchingStorageOrReceipts(): void
  {
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::never())->method('delete');
    $receipts = $this->createMock(IdempotentConsumerPort::class);
    $receipts->expects(self::never())->method('consume');
    $this->expectException(LogicException::class);
    new FileCleanupSubscriber(new DurableEventContext(), $receipts, $storage)->onCleanup(new FileCleanupRequested('obsolete'));
  }

  public function testDeletionFailureCanRetryAndACommittedReceiptSkipsDuplicateDelivery(): void
  {
    $context = new DurableEventContext();
    $receipts = new class () implements IdempotentConsumerPort {
      private bool $committed = false;

      public function consume(string $eventId, string $consumer, callable $operation): bool
      {
        TestCase::assertSame('event', $eventId);
        TestCase::assertSame(FileCleanupSubscriber::class, $consumer);
        if ($this->committed) {
          return false;
        }
        $operation();
        $this->committed = true;

        return true;
      }
    };
    $storage = $this->createMock(FileStoragePort::class);
    $calls = 0;
    $storage->expects(self::exactly(2))->method('delete')->with('obsolete')->willReturnCallback(static function () use (&$calls): void {
      if (1 === ++$calls) {
        throw new RuntimeException('storage unavailable');
      }
    });
    $subscriber = new FileCleanupSubscriber($context, $receipts, $storage);
    $event = new FileCleanupRequested('obsolete');

    try {
      $context->deliver('event', static fn () => $subscriber->onCleanup($event), 'actor');
      self::fail('Storage failures must propagate to bounded Messenger retries.');
    } catch (RuntimeException $failure) {
      self::assertSame('storage unavailable', $failure->getMessage());
    }
    self::assertNull($context->eventId());
    self::assertNull($context->actorUserId());
    $context->deliver('event', static fn () => $subscriber->onCleanup($event));
    $context->deliver('event', static fn () => $subscriber->onCleanup($event));
    self::assertSame(2, $calls);
  }

  public function testSequentialSystemDeliveryDoesNotInheritThePreviousActor(): void
  {
    $context = new DurableEventContext();
    $context->deliver('first', static function () use ($context): void {
      self::assertSame('actor', $context->actorUserId());
    }, 'actor');
    $context->reset();
    $context->deliver('second', static function () use ($context): void {
      self::assertSame('second', $context->eventId());
      self::assertNull($context->actorUserId());
    });
    self::assertNull($context->eventId());
    self::assertNull($context->actorUserId());
  }

  public function testAuthCleanupUsesOnlyItsOwnReceiptAdapter(): void
  {
    $context = new DurableEventContext();
    $main = $this->createMock(IdempotentConsumerPort::class);
    $main->expects(self::never())->method('consume');
    $auth = $this->createMock(IdempotentConsumerPort::class);
    $auth->expects(self::once())->method('consume')->with('auth-event', FileCleanupSubscriber::class, self::isCallable())->willReturnCallback(static function (string $id, string $consumer, callable $operation): bool {
      $operation();

      return true;
    });
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('delete')->with('avatars/obsolete.webp');
    $subscriber = new FileCleanupSubscriber($context, $main, $storage, $auth);
    $context->deliver('auth-event', static fn () => $subscriber->onCleanup(new FileCleanupRequested('avatars/obsolete.webp', 'auth')));
  }

  public function testHistoricalEventWithoutDatabaseKeepsMainReceipts(): void
  {
    $reflection = new ReflectionClass(FileCleanupRequested::class);
    $event = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('storagePath')->setValue($event, 'equipment/legacy.pdf');
    self::assertFalse($reflection->getProperty('owningDatabase')->isInitialized($event));
    $context = new DurableEventContext();
    $main = $this->createMock(IdempotentConsumerPort::class);
    $main->expects(self::once())->method('consume')->with('legacy-event', FileCleanupSubscriber::class, self::isCallable())->willReturnCallback(static function (string $id, string $consumer, callable $operation): bool {
      $operation();

      return true;
    });
    $auth = $this->createMock(IdempotentConsumerPort::class);
    $auth->expects(self::never())->method('consume');
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('delete')->with('equipment/legacy.pdf');
    $subscriber = new FileCleanupSubscriber($context, $main, $storage, $auth);
    $context->deliver('legacy-event', static fn () => $subscriber->onCleanup($event));
  }

  public function testMissingAuthReceiptBindingFailsBeforeDeletion(): void
  {
    $context = new DurableEventContext();
    $main = $this->createMock(IdempotentConsumerPort::class);
    $main->expects(self::never())->method('consume');
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::never())->method('delete');
    $subscriber = new FileCleanupSubscriber($context, $main, $storage);
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Auth file cleanup requires its auth consumer receipt adapter.');
    $context->deliver('auth-event', static fn () => $subscriber->onCleanup(new FileCleanupRequested('avatars/obsolete.webp', 'auth')));
  }
}
