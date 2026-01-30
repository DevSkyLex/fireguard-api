<?php

declare(strict_types=1);

namespace Audit\Application\UseCase\Command\RecordAuditEvent;

use Shared\Application\Message\ResultMessage;

/**
 * Result RecordAuditEventResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RecordAuditEventResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RecordAuditEventResult class.
   *
   * @since 1.0.0
   *
   * @param string $eventId the persisted event ID
   */
  public function __construct(
    public string $eventId,
  ) {
  }
}
