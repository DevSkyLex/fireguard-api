<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Time\WriteTimeEntry;

/**
 * WriteTimeEntryCommand.
 *
 * @category Intervention
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WriteTimeEntryCommand implements \Shared\Application\Message\CommandMessage
{
  /**
   * @since 1.0.0
   *
   * @param string $userId authenticated account identifier used for authorization
   * @param string $taskId intervention work-item identifier
   * @param string $action requested journal mutation: creation, correction, or cancellation
   * @param ?string $id stable resource identifier, retained across idempotent retries
   * @param ?string $memberId beneficiary for a new entry; null defaults to the actor, while corrections retain the existing beneficiary
   * @param ?string $workedOn organization-local calendar date on which the work was performed
   * @param ?int $minutes duration in whole minutes
   * @param ?string $note optional context supplied with the time entry
   * @param ?int $expectedRevision last explicitly reviewed time-entry revision
   */
  public function __construct(
    public string $userId,
    public string $taskId,
    public string $action,
    public ?string $id = null,
    public ?string $memberId = null,
    public ?string $workedOn = null,
    public ?int $minutes = null,
    public ?string $note = null,
    public ?int $expectedRevision = null,
  ) {
  }
}
