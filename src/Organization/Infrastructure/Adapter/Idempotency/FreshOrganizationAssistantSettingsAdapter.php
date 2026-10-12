<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Idempotency;

use Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort;
use Doctrine\DBAL\Connection;
use Organization\Domain\ValueObject\OrganizationAssistantSettings;

use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Class FreshOrganizationAssistantSettingsAdapter
 *
 * Rechecks the current owner opt-in before an assistant receipt can disclose conversation content.
 *
 * @category Adapter
 */
final readonly class FreshOrganizationAssistantSettingsAdapter implements AssistantOrganizationSettingsPort
{
  // #region Constructor
  /**
   * @param Connection $connection explicitly selected main connection
   */
  public function __construct(private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function isEnabledFor(string $organizationId): bool
  {
    return $this->settings($organizationId)->enabled ?? false;
  }

  /**
   * {@inheritdoc}
   */
  public function includeBusinessContextFor(string $organizationId): bool
  {
    return $this->settings($organizationId)->includeBusinessContext ?? false;
  }

  /**
   * @param string $organizationId current owner scope
   *
   * @return OrganizationAssistantSettings|null validated current settings
   */
  private function settings(string $organizationId): ?OrganizationAssistantSettings
  {
    $stored = $this->connection->fetchOne('SELECT settings FROM organizations WHERE id = :id', ['id' => $organizationId]);
    if (false === $stored) {
      return null;
    }
    $data = is_string($stored) ? json_decode($stored, true, 16, JSON_THROW_ON_ERROR) : $stored;

    return is_array($data) && is_array($data['assistant'] ?? []) ? OrganizationAssistantSettings::fromArray($data['assistant'] ?? []) : null;
  }
  // #endregion
}
