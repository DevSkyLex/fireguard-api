<?php

declare(strict_types=1);

namespace Audit\Application\UseCase\Command\RecordAuditEvent;

use DateTimeImmutable;
use Shared\Application\Message\CommandMessage;

/**
 * Command RecordAuditEventCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RecordAuditEventCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RecordAuditEventCommand class.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $metadata
   */
  public function __construct(
    public string $action,
    public string $actorType,
    public ?string $actorId = null,
    public ?string $actorEmail = null,
    public ?string $actorEmailHash = null,
    public ?string $subjectType = null,
    public ?string $subjectId = null,
    public ?string $clientId = null,
    public ?string $tenantId = null,
    public ?string $ipAddress = null,
    public ?string $ipHash = null,
    public ?string $userAgent = null,
    public array $metadata = [],
    public ?DateTimeImmutable $occurredAt = null,
  ) {
  }
}
