<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventSubscriber;

use LogicException;
use Shared\Application\Contract\Storage\FileCleanupRequested;
use Shared\Application\Port\Outbound\{DurableEventContextPort, FileStoragePort, IdempotentConsumerPort};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class FileCleanupSubscriber
 *
 * Deletes obsolete bytes after commit. A failed delete rolls back the receipt and retries durably.
 *
 * @category Event Subscriber
 */
final readonly class FileCleanupSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Keeps storage deletion separate from the owning media transaction.
   *
   * @access public
   *
   * @param DurableEventContextPort $context the outbox delivery identity
   * @param IdempotentConsumerPort $receipts the main-database consumer receipts
   * @param FileStoragePort $storage the idempotent storage deletion adapter
   * @param ?IdempotentConsumerPort $authReceipts the separately wired auth-database consumer receipts
   *
   * @return void
   */
  public function __construct(private DurableEventContextPort $context, private IdempotentConsumerPort $receipts, private FileStoragePort $storage, private ?IdempotentConsumerPort $authReceipts = null)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * Registers the stable event name used by the shared event dispatcher.
   *
   * @access public
   *
   * @return array<string, string> the event subscription
   */
  public static function getSubscribedEvents(): array
  {
    return ['shared.file_cleanup_requested' => 'onCleanup'];
  }

  /**
   * Method onCleanup
   *
   * Confirms a receipt only after deletion succeeds; replaying an already missing key is safe.
   *
   * @access public
   *
   * @param FileCleanupRequested $event the durable obsolete-key snapshot
   *
   * @return void
   */
  public function onCleanup(FileCleanupRequested $event): void
  {
    $id = $this->context->eventId();
    if (null === $id) {
      throw new LogicException('File cleanup must be delivered from the durable outbox.');
    }

    $receipts = match ($event->owningDatabase ?? 'main') {
      'main' => $this->receipts,
      'auth' => $this->authReceipts ?? throw new LogicException('Auth file cleanup requires its auth consumer receipt adapter.'),
      default => throw new LogicException('The file cleanup database is unsupported.'),
    };
    $receipts->consume($id, self::class, fn () => $this->storage->delete($event->storagePath));
  }
  // #endregion
}
