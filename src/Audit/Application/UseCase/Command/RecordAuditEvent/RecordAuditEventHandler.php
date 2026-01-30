<?php

declare(strict_types=1);

namespace Audit\Application\UseCase\Command\RecordAuditEvent;

use Audit\Application\Port\Outbound\AuditEventRepositoryPort;
use Audit\Domain\Model\AuditEvent;
use DateTimeImmutable;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\ValueObject\Uuid;

/**
 * Handler RecordAuditEventHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RecordAuditEventHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RecordAuditEventHandler class.
   *
   * @since 1.0.0
   *
   * @param UuidFactory $uuidFactory the UUID factory
   * @param AuditEventRepositoryPort $repository the audit repository
   */
  public function __construct(
    private UuidFactory $uuidFactory,
    private AuditEventRepositoryPort $repository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Persists the audit event into the ledger.
   *
   * @since 1.0.0
   *
   * @param RecordAuditEventCommand $command the command to handle
   *
   * @return RecordAuditEventResult the result
   */
  public function __invoke(RecordAuditEventCommand $command): RecordAuditEventResult
  {
    $eventId = $this->uuidFactory->create(Uuid::class);
    $occurredAt = $command->occurredAt ?? new DateTimeImmutable();

    $event = new AuditEvent(
      id: $eventId,
      action: $command->action,
      actorType: $command->actorType,
      actorId: $command->actorId,
      actorEmail: $command->actorEmail,
      actorEmailHash: $command->actorEmailHash,
      subjectType: $command->subjectType,
      subjectId: $command->subjectId,
      clientId: $command->clientId,
      tenantId: $command->tenantId,
      ipAddress: $command->ipAddress,
      ipHash: $command->ipHash,
      userAgent: $command->userAgent,
      metadata: $command->metadata,
      occurredAt: $occurredAt,
    );

    $stored = $this->repository->append($event);

    return new RecordAuditEventResult(eventId: $stored->id->value);
  }
  // #endregion
}
