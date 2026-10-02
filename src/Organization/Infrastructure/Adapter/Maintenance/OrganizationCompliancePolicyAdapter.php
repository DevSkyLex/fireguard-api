<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Maintenance;

use Doctrine\DBAL\{ArrayParameterType, Connection};
use Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy;
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Catalog\OrganizationComplianceDefaults;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationSettings};
use Shared\Domain\Exception\InvalidValueException;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Adapter OrganizationCompliancePolicyAdapter.
 *
 * Implements the Maintenance module's compliance policy port using the
 * organization's existing `OrganizationComplianceSettings` value object —
 * mirrors how `OrganizationNotificationPolicyService` resolves
 * `OrganizationNotificationPolicyPort` from the same settings aggregate.
 * Never throws on an unknown/malformed organization: falls back to "nothing
 * customized" (catalog defaults) so the recurring sweep can never crash on
 * one bad lookup.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationCompliancePolicyAdapter implements MaintenanceCompliancePolicyPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private ?Connection $connection = null,
  ) {
  }

  // #endregion
  // #region Methods
  /**
   * Method compliancePolicy
   *
   * Builds the maintenance compliance policy from organization settings, using module defaults when the organization or settings are unavailable.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   *
   * @return MaintenanceCompliancePolicy
   */
  public function compliancePolicy(string $organizationId): MaintenanceCompliancePolicy
  {
    if (null !== $this->connection) {
      return $this->compliancePolicies([$organizationId])[$organizationId];
    }

    try {
      $organization = $this->organizationRepository->findById(OrganizationId::fromString($organizationId));
    } catch (InvalidValueException) {
      return new MaintenanceCompliancePolicy([], OrganizationComplianceDefaults::REMINDER_WINDOW_DAYS);
    }

    $settings = $organization?->settings()->compliance;
    if (null === $settings) {
      return new MaintenanceCompliancePolicy([], OrganizationComplianceDefaults::REMINDER_WINDOW_DAYS);
    }

    return new MaintenanceCompliancePolicy($settings->effectiveInspectionPeriodicities(), $settings->reminderWindowDays);
  }

  /** @param list<string> $organizationIds
   * @return array<string, MaintenanceCompliancePolicy> */
  public function compliancePolicies(array $organizationIds): array
  {
    $policies = [];
    foreach ($organizationIds as $id) {
      $policies[$id] = new MaintenanceCompliancePolicy([], OrganizationComplianceDefaults::REMINDER_WINDOW_DAYS);
    }
    if ([] === $organizationIds) {
      return $policies;
    }
    if (null === $this->connection) {
      foreach ($organizationIds as $id) {
        $policies[$id] = $this->compliancePolicy($id);
      }

      return $policies;
    }
    /** @var list<array{id: string, settings: string}> $rows */
    $rows = $this->connection->fetchAllAssociative('SELECT id, settings FROM organizations WHERE id IN (:ids)', ['ids' => $organizationIds], ['ids' => ArrayParameterType::STRING]);
    foreach ($rows as $row) {
      /** @var array<string, mixed> $data */
      $data = json_decode($row['settings'], true, flags: JSON_THROW_ON_ERROR);
      $settings = OrganizationSettings::fromArray($data)->compliance;
      $policies[(string) $row['id']] = new MaintenanceCompliancePolicy($settings->effectiveInspectionPeriodicities(), $settings->reminderWindowDays);
    }

    return $policies;
  }
  // #endregion
}
