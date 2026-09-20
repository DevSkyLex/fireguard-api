<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Time\WriteTimeEntry;

use DateTimeZone;
use Intervention\Application\Port\Outbound\InterventionTimeEntryRepositoryPort;
use Intervention\Application\Service\InterventionTimeAccessPolicy;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException, InterventionPreconditionRequiredException, InterventionValidationException};
use Intervention\Domain\Model\TimeEntry\TimeEntry;
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, TransactionManagerPort, UuidGeneratorPort};
use Workload\Application\Port\Inbound\WorkloadCoordinationPort;

/**
 * WriteTimeEntryHandler.
 * Actual work never mutates the operational task/intervention or published dossier.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WriteTimeEntryHandler implements CommandHandler
{
  /**
   * @since 1.0.0
   *
   * @param InterventionTimeEntryRepositoryPort $entries persistence port for the independent time journal
   * @param InterventionTimeAccessPolicy $access authorizes journal operations for the current contributor
   * @param OrganizationWorkforceDirectoryPort $workforce organization-local membership and regional context directory
   * @param WorkloadCoordinationPort $coordination coordinates workload mutations under the caller-owned transaction
   * @param TransactionManagerPort $transactions main-database transaction boundary
   * @param UuidGeneratorPort $uuids generates stable identifiers when the caller did not supply one
   * @param ClockPort $clock clock used for calculation and journal timestamps
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

  /**
   * Persists an authorized, idempotent journal mutation without altering operational or published data.
   *
   * @since 1.0.0
   *
   * @param WriteTimeEntryCommand $command authorized use-case input to validate and persist
   *
   * @return WriteTimeEntryResult durably saved or idempotently replayed journal entry
   */
  public function __invoke(WriteTimeEntryCommand $command): WriteTimeEntryResult
  {
    return $this->transactions->transactional(function () use ($command): WriteTimeEntryResult {
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
      $context = $this->workforce->context($task->organizationId);
      if (null === $context) {
        throw InterventionNotFoundException::withId($task->organizationId);
      }
      $today = $this->clock->now()->setTimezone(new DateTimeZone($context->timezone))->format('Y-m-d');
      if (null !== $command->workedOn && $command->workedOn > $today) {
        throw new InterventionValidationException('Actual work cannot be recorded in the future.');
      }
      if ('create' === $command->action) {
        if (null !== $existing) {
          if ($existing->createdBy === $actor && $existing->memberId === ($command->memberId ?? $actor)) {
            $original = $existing->versions[0] ?? null;
            if (null !== $original && $original->workedOn === $command->workedOn && $original->minutes === $command->minutes && $original->note === $command->note) {
              return new WriteTimeEntryResult($existing);
            }
          }

          throw new InterventionConflictException('This time entry identifier has already been used.');
        }
        $entry = new TimeEntry($command->id ?? $this->uuids->generate(), $task->taskId, $beneficiary, $command->workedOn ?? '', $command->minutes ?? 0, $command->note);
      } else {
        if (null === $existing) {
          throw InterventionNotFoundException::withId($command->id ?? '');
        }
        if (null === $command->expectedRevision) {
          throw new InterventionPreconditionRequiredException('The time entry revision is required.');
        }
        // Idempotent replay of the immediately previous operation, never rebase.
        if ($existing->revision === $command->expectedRevision + 1 && $existing->updatedBy === $actor
          && (('cancel' === $command->action && $existing->cancelled)
            || ('correct' === $command->action && !$existing->cancelled && $existing->workedOn === $command->workedOn
              && $existing->minutes === $command->minutes && $existing->note === $command->note))) {
          return new WriteTimeEntryResult($existing);
        }
        $current = new TimeEntry($existing->id, $existing->workItemId, $existing->memberId, $existing->workedOn, $existing->minutes, $existing->note, $existing->revision, $existing->cancelled);
        $entry = 'cancel' === $command->action
          ? $current->cancel($command->expectedRevision)
          : $current->correct($command->expectedRevision, $command->workedOn ?? '', $command->minutes ?? 0, $command->note);
        if ($entry->revision === $existing->revision) {
          return new WriteTimeEntryResult($existing);
        }
      }

      return new WriteTimeEntryResult($this->entries->save($entry, $task->organizationId, $actor));
    });
  }
}
