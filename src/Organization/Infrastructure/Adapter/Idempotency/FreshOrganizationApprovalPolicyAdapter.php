<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Idempotency;

use Approval\Application\Contract\Policy\ApprovalPolicy;
use Approval\Application\Port\Outbound\{ApprovalActionCatalogPort, ApprovalPolicyPort};
use Doctrine\DBAL\Connection;
use Organization\Domain\ValueObject\OrganizationApprovalSettings;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;

use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Class FreshOrganizationApprovalPolicyAdapter
 *
 * Rechecks current four-eyes settings from the owning main row before replay disclosure without a managed settings aggregate.
 *
 * @category Adapter
 */
final readonly class FreshOrganizationApprovalPolicyAdapter implements ApprovalPolicyPort
{
  // #region Constructor
  /**
   * @param Connection $connection explicitly selected main connection
   * @param ApprovalActionCatalogPort $catalog installed action definitions and defaults
   */
  public function __construct(private Connection $connection, private ApprovalActionCatalogPort $catalog)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function policyFor(string $organizationId): ApprovalPolicy
  {
    $stored = $this->connection->fetchOne('SELECT settings FROM organizations WHERE id = :id', ['id' => $organizationId]);
    if (false === $stored) {
      throw new IdempotencyDeniedException('access_denied');
    }
    $data = is_string($stored) ? json_decode($stored, true, 16, JSON_THROW_ON_ERROR) : $stored;
    if (!is_array($data) || !is_array($data['approval'] ?? [])) {
      throw new IdempotencyDeniedException('access_denied');
    }
    $settings = OrganizationApprovalSettings::fromArray($data['approval'] ?? []);
    $rules = [];
    foreach ($this->catalog->descriptors() as $definition) {
      $rule = $settings->ruleFor($definition->value);
      $rules[$definition->value] = [...$rule, 'minSeverity' => $rule['minSeverity'] ?? $definition->defaultMinSeverity];
    }

    return new ApprovalPolicy($rules, $settings->allowSelfApproval, $settings->approvalTtlDays);
  }
  // #endregion
}
