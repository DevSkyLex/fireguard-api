<?php

declare(strict_types=1);

namespace Tests\Support;

use LogicException;
use Organization\Application\Contract\Quota\OrganizationQuotaDefinition;
use Organization\Application\Port\Outbound\{EquipmentStatisticsPort, FacilityStatisticsPort, InspectionStatisticsPort, OrganizationQuotaContributionPort};
use Organization\Application\Port\Outbound\Internal\{OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort};
use Organization\Application\Service\{OrganizationPermissionRegistry, OrganizationQuotaRegistry, OrganizationSettingsPolicyRegistry, OrganizationSystemRoleRegistry};
use Organization\Infrastructure\Adapter\Catalog\MemberQuotaContributionAdapter;

use function is_array;
use function is_int;
use function is_string;

/**
 * Class OrganizationCatalogTestFactory
 *
 * Composes production owner manifests explicitly for container-free compatibility tests.
 *
 * @category TestHelper
 */
final class OrganizationCatalogTestFactory
{
  /**
   * @return array<string,int> actual owner-contributed effective SLA defaults
   */
  public static function complianceSlaDefaults(): array
  {
    $value = self::settingsPolicies()->defaults('compliance')['non_conformity_sla_days'] ?? null;
    if (!is_array($value)) {
      throw new LogicException('Compliance SLA defaults must be a map.');
    }
    $defaults = [];
    foreach ($value as $key => $days) {
      if (!is_string($key) || !is_int($days)) {
        throw new LogicException('Compliance SLA default entries must be string-to-integer values.');
      }
      $defaults[$key] = $days;
    }

    return $defaults;
  }

  /**
   * @return array<string,string> actual owner-contributed effective periodicity defaults
   */
  public static function compliancePeriodicityDefaults(): array
  {
    $value = self::settingsPolicies()->defaults('compliance')['inspection_periodicity_defaults'] ?? null;
    if (!is_array($value)) {
      throw new LogicException('Compliance periodicity defaults must be a map.');
    }
    $defaults = [];
    foreach ($value as $key => $interval) {
      if (!is_string($key) || !is_string($interval)) {
        throw new LogicException('Compliance periodicity entries must be string-to-string values.');
      }
      $defaults[$key] = $interval;
    }

    return $defaults;
  }

  /**
   * @return OrganizationPermissionRegistry
   */
  public static function permissions(): OrganizationPermissionRegistry
  {
    return new OrganizationPermissionRegistry([
      new \Organization\Infrastructure\Adapter\Catalog\FireGuardOrganizationPermissionContributionAdapter(),
    ]);
  }

  /**
   * @return OrganizationQuotaRegistry
   */
  public static function quota(
    ?OrganizationMemberRepositoryPort $members = null,
    ?OrganizationInvitationRepositoryPort $invitations = null,
    ?FacilityStatisticsPort $facilities = null,
    ?EquipmentStatisticsPort $equipment = null,
    ?InspectionStatisticsPort $inspections = null,
  ): OrganizationQuotaRegistry {
    $manifests = [
      MemberQuotaContributionAdapter::definitions(),
      \Facility\Infrastructure\Adapter\OrganizationCatalog\FacilityQuotaContributionAdapter::definitions(),
      \Equipment\Infrastructure\Adapter\OrganizationCatalog\EquipmentQuotaContributionAdapter::definitions(),
      \Inspection\Infrastructure\Adapter\OrganizationCatalog\InspectionQuotaContributionAdapter::definitions(),
    ];
    $owners = [
      null !== $members && null !== $invitations ? new MemberQuotaContributionAdapter($members, $invitations) : null,
      null !== $facilities ? new \Facility\Infrastructure\Adapter\OrganizationCatalog\FacilityQuotaContributionAdapter($facilities) : null,
      null !== $equipment ? new \Equipment\Infrastructure\Adapter\OrganizationCatalog\EquipmentQuotaContributionAdapter($equipment) : null,
      null !== $inspections ? new \Inspection\Infrastructure\Adapter\OrganizationCatalog\InspectionQuotaContributionAdapter($inspections) : null,
    ];
    $fallback = new class () implements OrganizationQuotaContributionPort {
      /**
       * @return list<OrganizationQuotaDefinition>
       */
      public static function definitions(): array
      {
        return [];
      }

      public function usage(string $organizationId, string $resourceKey): int
      {
        return 0;
      }
    };

    return new OrganizationQuotaRegistry([
      new TestQuotaCatalogContribution($manifests, $owners, $fallback),
    ]);
  }

  /**
   * @return OrganizationSystemRoleRegistry
   */
  public static function systemRoles(): OrganizationSystemRoleRegistry
  {
    return new OrganizationSystemRoleRegistry([
      new \Organization\Infrastructure\Adapter\Catalog\OrganizationSystemRoleContributionAdapter(),
      new \Facility\Infrastructure\Adapter\OrganizationCatalog\FacilitySystemRoleContributionAdapter(),
      new \Equipment\Infrastructure\Adapter\OrganizationCatalog\EquipmentSystemRoleContributionAdapter(),
      new \Inspection\Infrastructure\Adapter\OrganizationCatalog\InspectionSystemRoleContributionAdapter(),
      new \Intervention\Infrastructure\Adapter\OrganizationCatalog\InterventionSystemRoleContributionAdapter(),
      new \Maintenance\Infrastructure\Adapter\OrganizationCatalog\MaintenanceSystemRoleContributionAdapter(),
      new \Compliance\Infrastructure\Adapter\OrganizationCatalog\ComplianceSystemRoleContributionAdapter(),
      new \Approval\Infrastructure\Adapter\OrganizationCatalog\ApprovalSystemRoleContributionAdapter(),
      new \Messaging\Infrastructure\Adapter\OrganizationCatalog\MessagingSystemRoleContributionAdapter(),
      new \Calendar\Infrastructure\Adapter\OrganizationCatalog\CalendarSystemRoleContributionAdapter(),
      new \Customer\Infrastructure\Adapter\OrganizationCatalog\CustomerSystemRoleContributionAdapter(),
    ]);
  }

  /**
   * @return OrganizationSettingsPolicyRegistry
   */
  public static function settingsPolicies(): OrganizationSettingsPolicyRegistry
  {
    return new OrganizationSettingsPolicyRegistry([
      new \Inspection\Infrastructure\Adapter\OrganizationCatalog\InspectionSettingsPolicyContributionAdapter(),
      new \Maintenance\Infrastructure\Adapter\OrganizationCatalog\MaintenanceSettingsPolicyContributionAdapter(),
      new \Equipment\Infrastructure\Adapter\OrganizationCatalog\EquipmentSettingsPolicyContributionAdapter(),
      new \Approval\Infrastructure\Adapter\OrganizationCatalog\ApprovalSettingsPolicyContributionAdapter(),
      new \Assistant\Infrastructure\Adapter\OrganizationCatalog\AssistantSettingsPolicyContributionAdapter(),
      new \Automation\Infrastructure\Adapter\OrganizationCatalog\AutomationSettingsPolicyContributionAdapter(),
    ]);
  }
}

/**
 * Class TestQuotaCatalogContribution
 *
 * Delegates counters to supplied test ports while keeping their production owner manifests.
 *
 * @category TestDouble
 */
final class TestQuotaCatalogContribution implements OrganizationQuotaContributionPort
{
  /**
   * @var array<string, OrganizationQuotaContributionPort>
   */
  private array $owners;

  /**
   * @param list<list<OrganizationQuotaDefinition>> $manifests
   * @param list<OrganizationQuotaContributionPort|null> $owners
   */
  public function __construct(array $manifests, array $owners, OrganizationQuotaContributionPort $fallback)
  {
    $indexed = [];
    foreach ($manifests as $position => $definitions) {
      foreach ($definitions as $definition) {
        $indexed[$definition->key] = $owners[$position] ?? $fallback;
      }
    }
    $this->owners = $indexed;
  }

  /**
   * @return list<OrganizationQuotaDefinition>
   */
  public static function definitions(): array
  {
    return [
      ...MemberQuotaContributionAdapter::definitions(),
      ...\Facility\Infrastructure\Adapter\OrganizationCatalog\FacilityQuotaContributionAdapter::definitions(),
      ...\Equipment\Infrastructure\Adapter\OrganizationCatalog\EquipmentQuotaContributionAdapter::definitions(),
      ...\Inspection\Infrastructure\Adapter\OrganizationCatalog\InspectionQuotaContributionAdapter::definitions(),
    ];
  }

  public function usage(string $organizationId, string $resourceKey): int
  {
    return $this->owners[$resourceKey]->usage($organizationId, $resourceKey);
  }
}
