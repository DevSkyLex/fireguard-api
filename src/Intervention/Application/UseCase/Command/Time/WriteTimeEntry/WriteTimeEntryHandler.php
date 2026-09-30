<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Time\WriteTimeEntry;

use DateTimeZone;
use Intervention\Application\Contract\Time\{TimeEntryTaskContext, TimeEntryView};
use Intervention\Application\Port\Outbound\InterventionTimeEntryRepositoryPort;
use Intervention\Application\Service\InterventionTimeAccessPolicy;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException, InterventionPreconditionRequiredException, InterventionValidationException};
use Intervention\Domain\Model\TimeEntry\TimeEntry;
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, TransactionManagerPort, UuidGeneratorPort};
use Workload\Application\Port\Inbound\WorkloadCoordinationPort;

/**
 * Class WriteTimeEntryHandler
 *
 * Writes the independent time journal without mutating operational tasks or published dossiers.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WriteTimeEntryHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies journal, access, workforce, transaction, identifier and clock capabilities.
   *
   * @access public
   * @since 1.0.0
   *
   * @param InterventionTimeEntryRepositoryPort $entries persistence port for the independent time journal
   * @param InterventionTimeAccessPolicy $access authorizes journal operations for the current contributor
   * @param OrganizationWorkforceDirectoryPort $workforce organization-local membership and regional context directory
   * @param WorkloadCoordinationPort $coordination coordinates workload mutations under the caller-owned transaction
   * @param TransactionManagerPort $transactions main-database transaction boundary
   * @param UuidGeneratorPort $uuids generates stable identifiers when the caller did not supply one
   * @param ClockPort $clock clock used for calculation and journal timestamps
   *
   * @return void
   */
  public function __construct(
    private InterventionTimeEntryRepositoryPort $entries,
    private InterventionTimeAccessPolicy $access,
    private OrganizationWorkforceDirectoryPort $workforce,
    private WorkloadCoordinationPort $coordination,
    private TransactionManagerPort $transactions,
    private UuidGeneratorPort $uuids,
    private ClockPort $clock,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * Runs a time-entry mutation inside the main transaction boundary.
   *
   * @access public
   *
   * Persists an authorized, idempotent journal mutation without altering operational or published data.
   * @since 1.0.0
   *
   * @param WriteTimeEntryCommand $command authorized use-case input to validate and persist
   *
   * @return WriteTimeEntryResult durably saved or idempotently replayed journal entry
   */
  public function __invoke(WriteTimeEntryCommand $command): WriteTimeEntryResult
  {
    return $this->transactions->transactional(fn (): WriteTimeEntryResult => $this->writeInsideTransaction($command));
  }

  /**
   * Method writeInsideTransaction
   *
   * Loads and authorizes the task context, coordinates workload, and applies the requested action.
   *
   * @access private
   *
   * @param WriteTimeEntryCommand $command validated journal mutation and actor
   *
   * @return WriteTimeEntryResult saved entry or idempotent replay result
   *
   * @throws InterventionNotFoundException when the task or requested entry is unavailable
   * @throws InterventionConflictException when an entry identifier was used by another mutation
   * @throws InterventionValidationException when the work date is in the future
   * @throws InterventionPreconditionRequiredException when a change omits the expected revision
   */
  private function writeInsideTransaction(WriteTimeEntryCommand $command): WriteTimeEntryResult
  {
    $task = $this->entries->context($command->taskId, true);
    if (null === $task) {
      throw InterventionNotFoundException::withId($command->taskId);
    }
    $actor = $this->access->actor($task, $command->userId);
    $existing = null === $command->id ? null : $this->entries->find($command->id);
    if (null !== $existing && $existing->workItemId !== $task->taskId) {
      throw InterventionNotFoundException::withId($command->id ?? '');
    }
    $beneficiary = $existing->memberId ?? $command->memberId ?? $actor;
    $actor = $this->access->assertWrite($task, $command->userId, $beneficiary, null !== $existing && $existing->memberId === $actor);
    $this->coordination->acquire($task->organizationId, [$beneficiary]);
    $this->assertWorkDate($task, $command);

    return 'create' === $command->action
      ? $this->createEntry($command, $task, $existing, $beneficiary, $actor)
      : $this->changeEntry($command, $task, $existing, $actor);
  }

  /**
   * Method assertWorkDate
   *
   * Checks that the work date does not follow the current date in the organization's timezone.
   *
   * @access private
   *
   * @param TimeEntryTaskContext $task task and organization context
   * @param WriteTimeEntryCommand $command requested work date
   *
   * @return void
   *
   * @throws InterventionNotFoundException when workforce timezone context is unavailable
   * @throws InterventionValidationException when actual work is dated in the future
   */
  private function assertWorkDate(TimeEntryTaskContext $task, WriteTimeEntryCommand $command): void
  {
    $context = $this->workforce->context($task->organizationId);
    if (null === $context) {
      throw InterventionNotFoundException::withId($task->organizationId);
    }
    $today = $this->clock->now()->setTimezone(new DateTimeZone($context->timezone))->format('Y-m-d');
    if (null !== $command->workedOn && $command->workedOn > $today) {
      throw new InterventionValidationException('Actual work cannot be recorded in the future.');
    }
  }

  /**
   * Method createEntry
   *
   * Creates a time entry or returns an exact replay of the same actor's create request.
   *
   * @access private
   *
   * @param WriteTimeEntryCommand $command create request
   * @param TimeEntryTaskContext $task task context owning the journal entry
   * @param TimeEntryView|null $existing entry already using a supplied identifier
   * @param string $beneficiary member receiving the recorded time
   * @param string $actor authorized actor writing the entry
   *
   * @return WriteTimeEntryResult saved entry or replay result
   *
   * @throws InterventionConflictException when the identifier belongs to a different create request
   */
  private function createEntry(WriteTimeEntryCommand $command, TimeEntryTaskContext $task, ?TimeEntryView $existing, string $beneficiary, string $actor): WriteTimeEntryResult
  {
    if (null !== $existing) {
      if ($this->isCreateReplay($command, $existing, $actor)) {
        return new WriteTimeEntryResult($existing);
      }

      throw new InterventionConflictException('This time entry identifier has already been used.');
    }
    $entry = new TimeEntry($command->id ?? $this->uuids->generate(), $task->taskId, $beneficiary, $command->workedOn ?? '', $command->minutes ?? 0, $command->note);

    return new WriteTimeEntryResult($this->entries->save($entry, $task->organizationId, $actor));
  }

  /**
   * Method isCreateReplay
   *
   * Checks whether an existing entry exactly matches the original create request and actor.
   *
   * @access private
   *
   * @param WriteTimeEntryCommand $command repeated create request
   * @param TimeEntryView $existing existing entry to compare
   * @param string $actor authorized actor issuing the request
   *
   * @return bool whether the request is an exact create replay
   */
  private function isCreateReplay(WriteTimeEntryCommand $command, TimeEntryView $existing, string $actor): bool
  {
    if ($existing->createdBy !== $actor || $existing->memberId !== ($command->memberId ?? $actor)) {
      return false;
    }
    $original = $existing->versions[0] ?? null;

    return null !== $original && $original->workedOn === $command->workedOn && $original->minutes === $command->minutes && $original->note === $command->note;
  }

  /**
   * Method changeEntry
   *
   * Cancels or corrects an existing entry using its expected revision and replay rules.
   *
   * @access private
   *
   * @param WriteTimeEntryCommand $command requested cancellation or correction
   * @param TimeEntryTaskContext $task task context owning the journal entry
   * @param TimeEntryView|null $existing entry being changed
   * @param string $actor authorized actor writing the change
   *
   * @return WriteTimeEntryResult saved entry or idempotent replay result
   *
   * @throws InterventionNotFoundException when the entry does not exist
   * @throws InterventionPreconditionRequiredException when the expected revision is missing
   */
  private function changeEntry(WriteTimeEntryCommand $command, TimeEntryTaskContext $task, ?TimeEntryView $existing, string $actor): WriteTimeEntryResult
  {
    if (null === $existing) {
      throw InterventionNotFoundException::withId($command->id ?? '');
    }
    if (null === $command->expectedRevision) {
      throw new InterventionPreconditionRequiredException('The time entry revision is required.');
    }
    if ($this->isChangeReplay($command, $existing, $actor)) {
      return new WriteTimeEntryResult($existing);
    }
    $current = new TimeEntry($existing->id, $existing->workItemId, $existing->memberId, $existing->workedOn, $existing->minutes, $existing->note, $existing->revision, $existing->cancelled);
    $entry = 'cancel' === $command->action
      ? $current->cancel($command->expectedRevision)
      : $current->correct($command->expectedRevision, $command->workedOn ?? '', $command->minutes ?? 0, $command->note);
    if ($entry->revision === $existing->revision) {
      return new WriteTimeEntryResult($existing);
    }

    return new WriteTimeEntryResult($this->entries->save($entry, $task->organizationId, $actor));
  }

  /**
   * Method isChangeReplay
   *
   * Recognizes only an identical operation against the immediately preceding revision.
   *
   * @access private
   *
   * @param WriteTimeEntryCommand $command repeated cancellation or correction
   * @param TimeEntryView $existing current persisted entry
   * @param string $actor authorized actor that performed the prior change
   *
   * @return bool whether the request repeats the immediately previous operation
   */
  private function isChangeReplay(WriteTimeEntryCommand $command, TimeEntryView $existing, string $actor): bool
  {
    // Only the immediately previous operation may be replayed; never rebase it.
    if ($existing->revision !== $command->expectedRevision + 1 || $existing->updatedBy !== $actor) {
      return false;
    }

    return ('cancel' === $command->action && $existing->cancelled)
      || ('correct' === $command->action && !$existing->cancelled && $existing->workedOn === $command->workedOn
        && $existing->minutes === $command->minutes && $existing->note === $command->note);
  }
  // #endregion
}
