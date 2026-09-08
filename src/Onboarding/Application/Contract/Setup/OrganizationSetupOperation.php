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
final readonly class OrganizationSetupOperation
{
  /**
   * @since 1.0.0
   *
   * @param array<string, mixed> $payload durable input without credentials
   * @param array<string, string> $resultIds owner-module identifiers required for replay
   */
  public function __construct(
    public string $stepKey,
    public string $itemKey,
    public array $payload,
    public ?string $resourceId = null,
    public array $resultIds = [],
  ) {
  }
}
