<?php

declare(strict_types=1);

namespace Onboarding\Application\Contract\Setup;

/**
 * Durable organization setup recovery.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationSetupSession
{
  /**
   * @since 1.0.0
   *
   * @param list<OrganizationSetupOperation> $operations durable operations
   */
  public function __construct(
    public string $id,
    public string $userId,
    public string $state,
    public ?string $nextStep,
    public ?string $organizationId,
    public bool $creationIntent,
    public array $operations,
  ) {
  }
}
