<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Messaging\Outbox;

use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Storage\FileCleanupRequested;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Infrastructure\Messaging\Outbox\TransactionalFileCleanupAdapter;

final class TransactionalFileCleanupAdapterTest extends TestCase
{
  public function testRecordsTheExactObsoleteKeyForDurableDelivery(): void
  {
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->with(self::callback(static fn (object $event): bool => $event instanceof FileCleanupRequested && 'equipment/obsolete.pdf' === $event->storagePath && 'main' === $event->owningDatabase));
    new TransactionalFileCleanupAdapter($events)->request('equipment/obsolete.pdf');
  }

  public function testAuthBindingCarriesTheOwningDatabaseToTheConsumer(): void
  {
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->with(self::callback(static fn (object $event): bool => $event instanceof FileCleanupRequested && 'avatars/obsolete.webp' === $event->storagePath && 'auth' === $event->owningDatabase));
    new TransactionalFileCleanupAdapter($events, 'auth')->request('avatars/obsolete.webp');
  }
}
