<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Plan\CreatePlan;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreatePlanCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreatePlanCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreatePlanCommand class.
   *
   * @since 1.0.0
   *
   * @param string $key the stable machine key
   * @param string $name the display name
   * @param array<string, int> $limits the per-resource quantity caps
   * @param ?string $description the optional description
   * @param bool $isActive whether the plan can be selected
   * @param bool $isDefault whether the plan is the catalog default
   * @param int $sortOrder the display order
   */
  public function __construct(
    public string $key,
    public string $name,
    public array $limits = [],
    public ?string $description = null,
    public bool $isActive = true,
    public bool $isDefault = false,
    public int $sortOrder = 0,
  ) {
  }
  // #endregion
}
