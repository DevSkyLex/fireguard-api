<?php

declare(strict_types=1);

namespace Workload\Application\UseCase\Command\Capacity\ChangeCapacity;

use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{TransactionManagerPort, UuidGeneratorPort};
use Shared\Domain\Exception\InvalidValueException;
use Workload\Application\Contract\Capacity\{CapacityExceptionView, CapacityWeekView};
use Workload\Application\Port\Inbound\WorkloadCoordinationPort;
use Workload\Application\Port\Outbound\CapacityRepositoryPort;
use Workload\Domain\Exception\{WorkloadAccessDeniedException, WorkloadNotFoundException};
use Workload\Domain\Model\Capacity\CapacitySchedule;
use Workload\Domain\ValueObject\{CapacityChange, CapacityException, CapacityWeek, LocalDate};

use function array_filter;
use function array_map;
use function array_values;

/**
 * ChangeCapacityHandler.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChangeCapacityHandler implements CommandHandler
{
  /**
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization organization membership and permission checks
   * @param OrganizationWorkforceDirectoryPort $workforce organization-local membership and regional context directory
   * @param CapacityRepositoryPort $capacities reads historical weeks and dated availability exceptions
   * @param WorkloadCoordinationPort $coordination coordinates workload mutations under the caller-owned transaction
   * @param TransactionManagerPort $transactionManager main-database transaction boundary for capacity changes
   * @param UuidGeneratorPort $uuids generates stable identifiers when the caller did not supply one
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationWorkforceDirectoryPort $workforce,
    private CapacityRepositoryPort $capacities,
    private WorkloadCoordinationPort $coordination,
    private TransactionManagerPort $transactionManager,
    private UuidGeneratorPort $uuids,
  ) {
  }

  /**
   * Validates and persists capacity changes under shared workload coordination.
   *
   * @since 1.0.0
   *
   * @param ChangeCapacityCommand $command authorized use-case input to validate and persist
   *
   * @return ChangeCapacityResult identifier of the persisted capacity change
   */
  public function __invoke(ChangeCapacityCommand $command): ChangeCapacityResult
  {
    return $this->transactionManager->transactional(function () use ($command): ChangeCapacityResult {
      if (!$this->authorization->isMemberOf($command->userId, $command->organizationId)) {
        throw new WorkloadNotFoundException('Organization not found.');
      }
      if (!$this->authorization->hasPermission($command->userId, $command->organizationId, 'organization.workload.manage')) {
        throw new WorkloadAccessDeniedException('Capacity management access is required.');
      }
      $actor = null;
      $targetExists = null === $command->memberId;
      $members = $this->workforce->members($command->organizationId);
      foreach ($members as $member) {
        if ($member->active && $member->userId === $command->userId) {
          $actor = $member->id;
        }
        if ($member->active && $member->id === $command->memberId) {
          $targetExists = true;
        }
      }
      if (null === $actor || !$targetExists) {
        throw new WorkloadNotFoundException('Active member not found.');
      }
      $this->coordination->acquire($command->organizationId, null === $command->memberId ? [] : [$command->memberId], null === $command->memberId);
      if ('cancel_exception' === $command->kind) {
        if (null === $command->memberId || null === $command->exceptionId || !$this->capacities->cancelException($command->organizationId, $command->memberId, $command->exceptionId, $actor)) {
          throw new WorkloadNotFoundException('Availability exception not found.');
        }

        return new ChangeCapacityResult($command->exceptionId);
      }
      $weeks = $this->capacities->weeks($command->organizationId);
      $exceptions = $this->capacities->exceptions($command->organizationId);
      $id = $this->uuids->generate();
      if ('week' === $command->kind) {
        $week = new CapacityWeek($command->weekMinutes);
        $date = LocalDate::fromString($command->effectiveOn ?? '');
        $candidate = new CapacityWeekView($id, $command->memberId ?? $command->organizationId, $date->value, $week->minutes);
        $weeks[] = $candidate;
      } elseif ('exception' === $command->kind && null !== $command->memberId) {
        $exception = new CapacityException(LocalDate::fromString($command->startsOn ?? ''), LocalDate::fromString($command->endsOn ?? ''), $command->minutes);
        $candidateException = new CapacityExceptionView($id, $command->memberId, $exception->startsOn->value, $exception->endsOn->value, $exception->minutes);
        $exceptions[] = $candidateException;
      } else {
        throw InvalidValueException::because('Invalid capacity change.');
      }
      // Validate all affected effective weeks and exception days while holding
      // the same member/organization locks used by scheduling and time writes.
      $toChange = static fn (CapacityWeekView $w): CapacityChange => new CapacityChange(LocalDate::fromString($w->effectiveOn), new CapacityWeek($w->minutes));
      $orgWeeks = array_map($toChange, array_values(array_filter($weeks, static fn ($w): bool => $w->scopeId === $command->organizationId)));
      foreach ($members as $member) {
        if (null !== $command->memberId && $command->memberId !== $member->id) {
          continue;
        }
        $memberExceptions = array_map(
          static fn ($e): CapacityException => new CapacityException(LocalDate::fromString($e->startsOn), LocalDate::fromString($e->endsOn), $e->minutes),
          array_values(array_filter($exceptions, static fn ($e): bool => $e->memberId === $member->id)),
        );
        $schedule = new CapacitySchedule($orgWeeks, array_map($toChange, array_values(array_filter($weeks, static fn ($w): bool => $w->scopeId === $member->id))), $memberExceptions);
        foreach ($memberExceptions as $exception) {
          for ($day = $exception->startsOn; $day->value <= $exception->endsOn->value; $day = $day->next()) {
            $schedule->on($day);
          }
        }
      }
      if (isset($candidate)) {
        $this->capacities->addWeek($command->organizationId, $candidate, $actor);
      } elseif (isset($candidateException)) {
        $this->capacities->addException($command->organizationId, $candidateException, $actor);
      }

      return new ChangeCapacityResult($id);
    });
  }
}
