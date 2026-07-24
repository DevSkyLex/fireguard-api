<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Schedule\SetMaintenanceScheduleOverride;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase SetMaintenanceScheduleOverrideCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetMaintenanceScheduleOverrideCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $scheduleId the maintenance schedule id value
   * @param ?string $intervalOverride the ISO-8601 interval override to set, or null to clear it
   */
  public function __construct(
    public string $userId,
    public string $scheduleId,
    public ?string $intervalOverride,
  ) {
  }
}
