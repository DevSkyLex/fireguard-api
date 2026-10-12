<?php

declare(strict_types=1);

namespace Audit\Infrastructure\EventSubscriber;

use Audit\Application\Contract\Event\AuditRecordRequestedEvent;
use Audit\Application\UseCase\Command\RecordAuditEvent\RecordAuditEventCommand;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\IdempotentConsumerPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordRequestedSubscriber
 *
 * Consumes sanitized requests with an auth-owned receipt in the ledger append transaction. Failures propagate for durable retry.
 *
 * @category EventSubscriber
 */
final readonly class AuditRecordRequestedSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the local ledger command bus and the explicitly auth-owned receipt adapter.
   *
   * @access public
   *
   * @param CommandBusPort $commandBus local ledger append dispatch
   * @param IdempotentConsumerPort $eventConsumer auth-owned atomic receipt capability
   *
   * @return void
   */
  public function __construct(private CommandBusPort $commandBus, private IdempotentConsumerPort $eventConsumer)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * Registers the generic durable request event only.
   *
   * @access public
   *
   * @return array<string, string> stable request event listener
   */
  public static function getSubscribedEvents(): array
  {
    return ['audit.audit_record_requested_event' => 'onRecordRequested'];
  }

  /**
   * Method onRecordRequested
   *
   * Reuses the original action/subject receipt key so historical deliveries do not append again after migration.
   *
   * @access public
   *
   * @param AuditRecordRequestedEvent $event committed request snapshot
   *
   * @return void
   */
  public function onRecordRequested(AuditRecordRequestedEvent $event): void
  {
    $record = $event->record;
    $command = new RecordAuditEventCommand(
      action: $record->action,
      actorType: $record->actorType,
      actorId: $record->actorId,
      actorEmail: $record->actorEmail,
      actorEmailHash: $record->actorEmailHash,
      subjectType: $record->subjectType,
      subjectId: $record->subjectId,
      clientId: $record->clientId,
      tenantId: $record->tenantId,
      organizationId: $record->organizationId,
      ipAddress: $record->ipAddress,
      ipHash: $record->ipHash,
      userAgent: $record->userAgent,
      metadata: $record->metadata,
      occurredAt: $record->occurredAt,
    );
    $this->eventConsumer->consume(
      $event->requestId,
      'audit:' . $record->action . ':' . $record->subjectType . ':' . $record->subjectId,
      function () use ($command): void { $this->commandBus->dispatch($command); },
    );
  }
  // #endregion
}
