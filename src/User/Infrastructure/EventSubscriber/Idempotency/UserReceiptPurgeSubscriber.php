<?php

declare(strict_types=1);

namespace User\Infrastructure\EventSubscriber\Idempotency;

use LogicException;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use User\Application\Contract\Event\UserReceiptPurgeRequestedEvent;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

/**
 * Retries auth and main receipt erasure from a committed deletion outbox event.
 * Each database erases independently; an interruption safely repeats the deletion.
 *
 * @category EventSubscriber
 */
final readonly class UserReceiptPurgeSubscriber implements EventSubscriberInterface
{
  /**
   * @param HttpIdempotencyPort $auth explicit auth receipt engine
   * @param HttpIdempotencyPort $main explicit main receipt engine
   * @param UserSecurityRevisionPort $users fresh account existence guard
   */
  public function __construct(private HttpIdempotencyPort $auth, private HttpIdempotencyPort $main, private UserSecurityRevisionPort $users)
  {
  }

  /**
   * @return array<string,string> committed deletion listener
   */
  public static function getSubscribedEvents(): array
  {
    return ['user.user_receipt_purge_requested_event' => 'purge'];
  }

  /**
   * @param UserReceiptPurgeRequestedEvent $event durable permanent deletion fact
   */
  public function purge(UserReceiptPurgeRequestedEvent $event): void
  {
    if (null !== $this->users->current($event->userId)) {
      throw new LogicException('Receipt erasure requires a confirmed permanent account deletion.');
    }
    $this->auth->purgePrincipal($event->userId);
    $this->main->purgePrincipal($event->userId);
  }
}
