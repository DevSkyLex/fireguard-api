<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Idempotency;

use Automation\Application\Contract\Policy\AutomationPolicy;
use Automation\Application\Port\Outbound\AutomationPolicyPort;
use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\OrganizationSettingsPolicyCatalogPort;
use Organization\Domain\ValueObject\OrganizationSettings;

use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Class FreshOrganizationAutomationPolicyAdapter
 *
 * Rechecks the current owner automation toggle and registered effective SLA defaults without managed organization settings.
 *
 * @category Adapter
 */
final readonly class FreshOrganizationAutomationPolicyAdapter implements AutomationPolicyPort
{
  // #region Constructor
  /**
   * @param Connection $connection explicitly selected main connection
   * @param OrganizationSettingsPolicyCatalogPort $settingsPolicies installed owner defaults
   */
  public function __construct(private Connection $connection, private OrganizationSettingsPolicyCatalogPort $settingsPolicies)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function policyFor(string $organizationId): AutomationPolicy
  {
    $stored = $this->connection->fetchOne('SELECT settings FROM organizations WHERE id = :id', ['id' => $organizationId]);
    $data = is_string($stored) ? json_decode($stored, true, 16, JSON_THROW_ON_ERROR) : $stored;
    $fields = [];
    if (is_array($data)) {
      foreach ($data as $key => $value) {
        if (is_string($key)) {
          $fields[$key] = $value;
        }
      }
    }
    $settings = OrganizationSettings::fromArray($fields, $this->settingsPolicies->allDefaults());

    return new AutomationPolicy(false !== $stored && $settings->automation->autoCreateInterventionOnCriticalNc, $settings->compliance->effectiveNonConformitySlaDays());
  }
  // #endregion
}
