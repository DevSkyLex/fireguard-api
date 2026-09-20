<?php

declare(strict_types=1);

namespace Workload\Application\Service;

use DateTimeZone;
use Intervention\Application\Contract\Workload\InterventionWorkContribution;
use Intervention\Application\Port\Inbound\InterventionWorkloadContributionsPort;
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use Shared\Application\Port\Outbound\ClockPort;
use Workload\Application\Contract\Planning\{WorkloadAssessment, WorkloadConfirmationRequired, WorkloadPlanningSnapshot};
use Workload\Application\Contract\Projection\WorkloadProjectionView;
use Workload\Application\Port\Inbound\{WorkloadPlanningPort, WorkloadProjectionPort};
use Workload\Application\Port\Outbound\WorkloadConfirmationSignerPort;
use Workload\Domain\Exception\WorkloadNotFoundException;

use function array_unique;
use function array_values;
use function hash_equals;
use function in_array;
use function json_encode;
use function ksort;
use function max;
use function sort;

use const JSON_THROW_ON_ERROR;
use const SORT_STRING;

/**
 * WorkloadPlanningService.
 * Captures are taken under the shared workload locks by the writing module.
 * A confirmation is bound to both the prior revision fingerprint and the new
 * daily totals. Random creation identifiers do not invalidate an identical retry.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkloadPlanningService implements WorkloadPlanningPort
{
  /**
   * @since 1.0.0
   *
   * @param WorkloadProjectionPort $projector computes daily demand without relying on paginated UI data
   * @param InterventionWorkloadContributionsPort $contributions reads task demand and actual time through Intervention contracts
   * @param OrganizationWorkforceDirectoryPort $workforce organization-local membership and regional context directory
   * @param ClockPort $clock clock used for calculation and journal timestamps
   * @param WorkloadConfirmationSignerPort $signer binds confirmation tokens to the relevant assessment
   */
  public function __construct(
    private WorkloadProjectionPort $projector,
    private InterventionWorkloadContributionsPort $contributions,
    private OrganizationWorkforceDirectoryPort $workforce,
    private ClockPort $clock,
    private WorkloadConfirmationSignerPort $signer,
  ) {
  }

  /**
   * Captures the complete relevant demand before a coordinated planning mutation.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param list<string> $memberIds
   *
   * @return WorkloadPlanningSnapshot relevant demand captured before the mutation
   */
  public function capture(string $organizationId, array $memberIds): WorkloadPlanningSnapshot
  {
    $members = array_values(array_unique($memberIds));
    sort($members, SORT_STRING);
    $context = $this->workforce->context($organizationId);
    if (null === $context) {
      throw new WorkloadNotFoundException('Organization not found.');
    }
    $today = $this->clock->now()->setTimezone(new DateTimeZone($context->timezone))->format('Y-m-d');
    $to = $this->horizon($organizationId, $members, $today);

    return new WorkloadPlanningSnapshot($organizationId, $members, $this->projector->project($organizationId, $today, $to, $members));
  }

  /**
   * Compares daily overload after replacing the proposed task contributions.
   *
   * @since 1.0.0
   *
   * @param WorkloadPlanningSnapshot $before snapshot captured before the proposed planning mutation
   * @param list<InterventionWorkContribution> $replacements
   *
   * @return WorkloadAssessment daily before/after overload and assessment-bound consent token
   */
  public function assess(WorkloadPlanningSnapshot $before, array $replacements = []): WorkloadAssessment
  {
    $from = $before->projection->view->startsOn;
    $to = $this->horizon($before->organizationId, $before->memberIds, $before->projection->view->endsOn, $replacements);
    $after = $this->projector->project($before->organizationId, $from, $to, $before->memberIds, $replacements);
    $prior = self::totals($before->projection->view);
    $current = self::totals($after->view);
    $increases = [];
    $labels = $this->workforce->labels($before->organizationId, $before->memberIds);
    foreach ($current as $key => $day) {
      $previous = $prior[$key]['overload'] ?? 0;
      if ($day['overload'] > $previous) {
        $increases[] = [
          'memberId' => $day['memberId'], 'date' => $day['date'], 'reason' => $day['reason'],
          'memberName' => $labels[$day['memberId']] ?? $day['memberId'],
          'beforeMinutes' => $previous, 'afterMinutes' => $day['overload'], 'capacityMinutes' => $day['capacity'],
        ];
      }
    }
    $token = $this->signer->sign(json_encode([$before->organizationId, $before->memberIds, $before->projection->fingerprint, $current], JSON_THROW_ON_ERROR));

    return new WorkloadAssessment([] !== $increases, $increases, $after->view->completeness, $token);
  }

  /**
   * Rejects new or increased overload unless consent matches the current assessment.
   *
   * @since 1.0.0
   *
   * @param WorkloadPlanningSnapshot $before snapshot captured before the proposed planning mutation
   * @param ?string $confirmationToken consent bound to the exact server assessment, never blanket approval
   *
   * @return void completes without returning a value
   */
  public function assertAccepted(WorkloadPlanningSnapshot $before, ?string $confirmationToken): void
  {
    $assessment = $this->assess($before);
    if ($assessment->confirmationRequired && (null === $confirmationToken || !hash_equals($assessment->confirmationToken, $confirmationToken))) {
      throw new WorkloadConfirmationRequired($assessment);
    }
  }

  /**
   * Extends the assessment through the latest relevant task date, not only the visible week.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param list<string> $members
   * @param string $minimum earliest permissible end date of the assessment horizon
   * @param list<InterventionWorkContribution> $replacements
   *
   * @return string last local date required to assess all relevant demand
   */
  private function horizon(string $organizationId, array $members, string $minimum, array $replacements = []): string
  {
    $context = $this->workforce->context($organizationId);
    if (null === $context) {
      throw new WorkloadNotFoundException('Organization not found.');
    }
    $to = $minimum;
    foreach ([...$this->contributions->tasks($organizationId, $context->timezone), ...$replacements] as $task) {
      if (in_array($task->memberId, $members, true) && null !== $task->endsOn && 'none' !== $task->commitment) {
        $to = max($to, $task->endsOn);
      }
    }

    return $to;
  }

  /**
   * Builds stable daily totals, including committed demand with no available day.
   *
   * @since 1.0.0
   *
   * @param WorkloadProjectionView $view projection view or persisted record being translated
   *
   * @return array<string, array{memberId: string, date: ?string, reason: string, capacity: ?int, actual: int, remaining: int, draft: int, overload: int}>
   */
  private static function totals(WorkloadProjectionView $view): array
  {
    $totals = [];
    foreach ($view->members as $member) {
      foreach ($member->days as $day) {
        $totals[$member->memberId . ':' . $day->date] = [
          'memberId' => $member->memberId, 'date' => $day->date, 'reason' => 'daily_overload', 'capacity' => $day->capacityMinutes,
          'actual' => $day->actualMinutes, 'remaining' => $day->remainingMinutes, 'draft' => $day->draftMinutes, 'overload' => $day->overloadMinutes ?? 0,
        ];
      }
      $unavailable = 0;
      foreach ($member->unallocated as $task) {
        if ('committed' === $task->commitment && 'no_available_day' === $task->reason) {
          $unavailable += $task->remainingMinutes ?? 0;
        }
      }
      if ($unavailable > 0) {
        $totals[$member->memberId . ':unavailable'] = [
          'memberId' => $member->memberId, 'date' => null, 'reason' => 'no_available_day', 'capacity' => 0,
          'actual' => 0, 'remaining' => $unavailable, 'draft' => 0, 'overload' => $unavailable,
        ];
      }
    }
    ksort($totals);

    return $totals;
  }
}
