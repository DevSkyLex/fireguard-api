<?php

declare(strict_types=1);

namespace Compliance\Infrastructure\Adapter\Assistant;

use Assistant\Application\Contract\Context\{AssistantContextBudget, AssistantContextFragment, AssistantContextScope};
use Assistant\Application\Port\Outbound\AssistantContextProviderPort;
use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Application\Service\{ComplianceRegisterAggregator, ComplianceStatusPolicy};
use Compliance\Domain\ValueObject\ComplianceStatus;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Throwable;

use function array_diff;
use function array_map;
use function count;
use function sprintf;

/**
 * Adapter ComplianceAssistantContextProviderAdapter.
 *
 * Implements the Assistant module's business-context seam
 * (`assistant.context_provider`) for the ORGANIZATION-WIDE compliance
 * summary — the same aggregate `GetComplianceOverviewHandler` serves on
 * `GET /organizations/{organizationId}/compliance`, reusing
 * `ComplianceRegisterAggregator`/`ComplianceStatusPolicy` directly (both are
 * THIS module's own services, not a cross-module dependency) rather than
 * duplicating the fan-out or re-querying anything.
 *
 * Permission-gated on the SAME dependency set the JSON endpoint requires
 * (`OrganizationPermissionCatalog::complianceReadDependencies()`, checked in
 * {@see self::supports()}): an asking member missing any of them never
 * reaches {@see self::provide()} — a context block is not a permission
 * bypass.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ComplianceAssistantContextProviderAdapter implements AssistantContextProviderPort
{
  // #region Constants
  private const string SOURCE_KEY = 'compliance.summary';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param ComplianceRegisterAggregator $aggregator the compliance register aggregator
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private ComplianceRegisterAggregator $aggregator,
  ) {
  }
  // #endregion

  // #region Methods
  public function supports(string $organizationId, AssistantContextScope $scope): bool
  {
    $granted = $this->authorization->getUserPermissions($scope->actorUserId, $organizationId);

    return [] === array_diff(OrganizationPermissionCatalog::complianceReadDependencies(), $granted);
  }

  public function provide(string $organizationId, AssistantContextScope $scope, AssistantContextBudget $budget): AssistantContextFragment
  {
    try {
      $facilities = $this->aggregator->buildFacilityViews($organizationId);
    } catch (Throwable) {
      return AssistantContextFragment::empty(self::SOURCE_KEY);
    }

    if ([] === $facilities) {
      return AssistantContextFragment::empty(self::SOURCE_KEY);
    }

    $organizationStatus = ComplianceStatusPolicy::worstOf(array_map(
      static fn (FacilityComplianceView $view): ComplianceStatus => $view->status,
      $facilities,
    ));

    $statusCounts = [
      ComplianceStatus::NON_COMPLIANT->value => 0,
      ComplianceStatus::AT_RISK->value => 0,
      ComplianceStatus::COMPLIANT->value => 0,
      ComplianceStatus::NOT_APPLICABLE->value => 0,
    ];

    $trackedEquipmentCount = 0;
    $upToDateEquipmentCount = 0;
    $dueSoonEquipmentCount = 0;
    $overdueEquipmentCount = 0;
    $unscheduledEquipmentCount = 0;
    $openLowNonConformityCount = 0;
    $openMediumNonConformityCount = 0;
    $openHighNonConformityCount = 0;
    $openCriticalNonConformityCount = 0;

    foreach ($facilities as $facility) {
      ++$statusCounts[$facility->status->value];
      $trackedEquipmentCount += $facility->trackedEquipmentCount();
      $upToDateEquipmentCount += $facility->upToDateEquipmentCount;
      $dueSoonEquipmentCount += $facility->dueSoonEquipmentCount;
      $overdueEquipmentCount += $facility->overdueEquipmentCount;
      $unscheduledEquipmentCount += $facility->unscheduledEquipmentCount;
      $openLowNonConformityCount += $facility->openLowNonConformityCount;
      $openMediumNonConformityCount += $facility->openMediumNonConformityCount;
      $openHighNonConformityCount += $facility->openHighNonConformityCount;
      $openCriticalNonConformityCount += $facility->openCriticalNonConformityCount;
    }

    $text = sprintf(
      "Compliance summary:\n"
      . "- Organization status: %s\n"
      . "- Facilities (%d total): %d non_compliant, %d at_risk, %d compliant, %d not_applicable\n"
      . "- Tracked equipment: %d (%d up to date, %d due soon, %d overdue); %d unscheduled\n"
      . '- Open non-conformities: %d critical, %d high, %d medium, %d low',
      $organizationStatus->value,
      count($facilities),
      $statusCounts[ComplianceStatus::NON_COMPLIANT->value],
      $statusCounts[ComplianceStatus::AT_RISK->value],
      $statusCounts[ComplianceStatus::COMPLIANT->value],
      $statusCounts[ComplianceStatus::NOT_APPLICABLE->value],
      $trackedEquipmentCount,
      $upToDateEquipmentCount,
      $dueSoonEquipmentCount,
      $overdueEquipmentCount,
      $unscheduledEquipmentCount,
      $openCriticalNonConformityCount,
      $openHighNonConformityCount,
      $openMediumNonConformityCount,
      $openLowNonConformityCount,
    );

    return AssistantContextFragment::withText(self::SOURCE_KEY, $text);
  }
  // #endregion
}
