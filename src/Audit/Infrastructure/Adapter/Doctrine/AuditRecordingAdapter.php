<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Adapter\Doctrine;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Contract\Event\AuditRecordRequestedEvent;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Audit\Infrastructure\Service\AuditRecordPayloadFactory;
use Doctrine\DBAL\Connection;
use LogicException;
use Shared\Application\Port\Outbound\{DurableEventContextPort, EventDispatcherPort, UuidGeneratorPort};

/**
 * Class AuditRecordingAdapter
 *
 * Enqueues audit requests on the producer's active connection. It never opens a ledger transaction inside a main producer transaction.
 *
 * @category Adapter
 */
final readonly class AuditRecordingAdapter implements AuditRecordingPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives both explicitly owned outboxes and their transaction state, without sharing a transaction across databases.
   *
   * @access public
   *
   * @param Connection $authConnection auth producer connection
   * @param Connection $mainConnection main producer connection
   * @param EventDispatcherPort $authEvents strict auth outbox dispatcher
   * @param EventDispatcherPort $mainEvents strict main outbox dispatcher
   * @param UuidGeneratorPort $ids new request identity generator
   * @param DurableEventContextPort $eventContext original delivery identity for retry deduplication
   * @param AuditRecordPayloadFactory $payloadFactory privacy and context projection
   *
   * @return void
   */
  public function __construct(
    private Connection $authConnection,
    private Connection $mainConnection,
    private EventDispatcherPort $authEvents,
    private EventDispatcherPort $mainEvents,
    private UuidGeneratorPort $ids,
    private DurableEventContextPort $eventContext,
    private AuditRecordPayloadFactory $payloadFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method requestRecord
   *
   * Persists only a sanitized request; delivery later appends the auth ledger and its receipt atomically.
   *
   * @access public
   *
   * @param AuditRecordRequest $request owner-projected fact
   *
   * @return void
   *
   * @throws LogicException when concurrent auth/main transactions make ownership ambiguous
   */
  public function requestRecord(AuditRecordRequest $request): void
  {
    $authActive = $this->authConnection->isTransactionActive();
    $mainActive = $this->mainConnection->isTransactionActive();
    if ($authActive && $mainActive) {
      throw new LogicException('Audit request ownership is ambiguous while auth and main transactions are both active.');
    }
    $event = new AuditRecordRequestedEvent(
      $this->eventContext->eventId() ?? $this->ids->generate(),
      $this->payloadFactory->create($request),
    );
    if ($mainActive) {
      $this->mainEvents->dispatch($event);

      return;
    }
    if ($authActive) {
      $this->authEvents->dispatch($event);

      return;
    }

    $this->authConnection->transactional(function () use ($event): void { $this->authEvents->dispatch($event); });
  }
  // #endregion
}
