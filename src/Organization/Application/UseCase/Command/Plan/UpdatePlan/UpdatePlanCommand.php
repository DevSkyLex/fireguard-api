<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Plan\UpdatePlan;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdatePlanCommand.
 *
 * Carries the plan fields to update. Every mutable field is nullable: a `null`
 * value means "leave unchanged". The plan key is immutable and not updatable.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdatePlanCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UpdatePlanCommand class.
   *
   * @since 1.0.0
   *
   * @param string $planId the plan identifier
   * @param ?string $name the new display name, or null to leave unchanged
   * @param ?string $description the new description, or null to leave unchanged
   * @param ?array<string, int> $limits the new per-resource caps, or null to leave unchanged
   * @param ?bool $isActive the new active flag, or null to leave unchanged
   * @param ?bool $isDefault the new default flag, or null to leave unchanged
   * @param ?int $sortOrder the new display order, or null to leave unchanged
   */
  public function __construct(
    public string $planId,
    public ?string $name = null,
    public ?string $description = null,
    public ?array $limits = null,
    public ?bool $isActive = null,
    public ?bool $isDefault = null,
    public ?int $sortOrder = null,
  ) {
  }
  // #endregion
}
