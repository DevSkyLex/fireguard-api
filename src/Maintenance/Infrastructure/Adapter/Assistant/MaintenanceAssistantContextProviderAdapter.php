<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Assistant;

use Assistant\Application\Contract\Context\{AssistantContextBudget, AssistantContextFragment, AssistantContextScope};
use Assistant\Application\Port\Outbound\AssistantContextProviderPort;
use DateTimeImmutable;
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleView;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Throwable;

use function array_merge;
use function implode;
use function sprintf;
use function usort;

/**
 * Adapter MaintenanceAssistantContextProviderAdapter.
 *
 * Implements the Assistant module's business-context seam
 * (`assistant.context_provider`) for upcoming maintenance due dates — what
 * feeds the mockup's "What's blocking the campaign?" suggestion.
 *
 * Permission-gated on `organization.maintenance.read`, checked in
 * {@see self::supports()}: an asking member missing it never reaches
 * {@see self::provide()} — a context block is not a permission bypass.
 *
 * Reuses {@see MaintenanceScheduleRepositoryPort::list()} verbatim (this
 * module's own, already-tested domain-facing port) — no new DQL is
 * introduced by this adapter, so no dedicated integration test is needed
 * for it (unlike Inspection's sibling adapter).
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceAssistantContextProviderAdapter implements AssistantContextProviderPort
{
  // #region Constants
  private const string SOURCE_KEY = 'maintenance.upcoming_due_dates';

  private const string REQUIRED_READ_PERMISSION = 'organization.maintenance.read';

  private const int MAX_ITEMS_PER_STATUS = 5;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param MaintenanceScheduleRepositoryPort $schedules the maintenance schedule repository port
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private MaintenanceScheduleRepositoryPort $schedules,
  ) {
  }
  // #endregion

  // #region Methods
  public function supports(string $organizationId, AssistantContextScope $scope): bool
  {
    return $this->authorization->hasPermission($scope->actorUserId, $organizationId, self::REQUIRED_READ_PERMISSION);
  }

  public function provide(string $organizationId, AssistantContextScope $scope, AssistantContextBudget $budget): AssistantContextFragment
  {
    try {
      $overduePage = $this->schedules->list($organizationId, null, null, 'overdue', null, 1, self::MAX_ITEMS_PER_STATUS);
      $dueSoonPage = $this->schedules->list($organizationId, null, null, 'due_soon', null, 1, self::MAX_ITEMS_PER_STATUS);
    } catch (Throwable) {
      return AssistantContextFragment::empty(self::SOURCE_KEY);
    }

    if (0 === $overduePage->total && 0 === $dueSoonPage->total) {
      return AssistantContextFragment::empty(self::SOURCE_KEY);
    }

    $lines = [sprintf(
      'Upcoming maintenance due dates: %d overdue, %d due soon (soonest first, showing up to %d of each below):',
      $overduePage->total,
      $dueSoonPage->total,
      self::MAX_ITEMS_PER_STATUS,
    )];

    $items = array_merge($overduePage->items, $dueSoonPage->items);
    usort($items, static fn (MaintenanceScheduleView $left, MaintenanceScheduleView $right): int => self::sortableDueAt($left) <=> self::sortableDueAt($right));

    foreach ($items as $item) {
      $lines[] = sprintf(
        '- %s equipment (%s%s)',
        $item->equipmentType,
        $item->dueStatus,
        $item->nextDueAt instanceof DateTimeImmutable ? ', due ' . $item->nextDueAt->format('Y-m-d') : '',
      );
    }

    return AssistantContextFragment::withText(self::SOURCE_KEY, implode("\n", $lines));
  }

  /**
   * Method sortableDueAt.
   *
   * @static
   *
   * A never-inspected `overdue` schedule carries a `null` `nextDueAt`
   * (treated as immediately due — see `MaintenanceScheduleRecomputePolicy`);
   * sorted first by substituting the earliest possible instant.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleView $view the maintenance schedule view
   *
   * @return DateTimeImmutable the due date to sort by
   */
  private static function sortableDueAt(MaintenanceScheduleView $view): DateTimeImmutable
  {
    return $view->nextDueAt ?? new DateTimeImmutable('@0');
  }
  // #endregion
}
