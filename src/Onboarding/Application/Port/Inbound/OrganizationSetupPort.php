<?php

declare(strict_types=1);

namespace Onboarding\Application\Port\Inbound;

use Onboarding\Application\Contract\Setup\{OrganizationSetupContext, OrganizationSetupOperation};

/**
 * Durable organization setup recovery.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationSetupPort
{
  /**
   * @since 1.0.0
   *
   * @param list<mixed> $items batch to persist
   */
  public function prepare(string $userId, string $sessionId, string $stepKey, array $items): void;

  /**
   * @since 1.0.0
   * Must run in the owner's main transaction, before quota or resource writes.
   *
   * @param array<string,mixed> $payload actual owner-command input
   */
  public function begin(OrganizationSetupContext $context, string $stepKey, ?string $organizationId, array $payload): OrganizationSetupOperation;

  /**
   * @since 1.0.0
   * Must run in the same transaction as begin and the durable resource write.
   *
   * @param array<string,string> $resultIds identifiers required by the owner's replay result
   */
  public function complete(OrganizationSetupContext $context, string $stepKey, string $resourceId, array $resultIds = []): void;
}
