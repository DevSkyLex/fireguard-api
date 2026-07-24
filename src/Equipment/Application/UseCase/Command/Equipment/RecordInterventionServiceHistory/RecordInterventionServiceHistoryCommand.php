<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\RecordInterventionServiceHistory;

use DateTimeImmutable;
use Shared\Application\Message\CommandMessage;

/**
 * UseCase RecordInterventionServiceHistoryCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RecordInterventionServiceHistoryCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $interventionId the published intervention identifier
   * @param string $publicationId the completed publication identifier
   * @param DateTimeImmutable $occurredAt when the intervention published (the service instant)
   */
  public function __construct(
    public string $organizationId,
    public string $interventionId,
    public string $publicationId,
    public DateTimeImmutable $occurredAt,
  ) {
  }
  // #endregion
}
