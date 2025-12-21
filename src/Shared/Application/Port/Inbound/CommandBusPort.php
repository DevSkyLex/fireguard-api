<?php

declare(strict_types=1);

namespace Shared\Application\Port\Inbound;

use Shared\Application\Message\CommandMessage;
use Shared\Application\Message\ResultMessage;

/**
 * Port CommandBusPort.
 *
 * Port used to send commands
 * to the application.
 *
 * @category Inbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CommandBusPort
{
  // #region Methods
  /**
   * Method dispatch.
   *
   * Dispatch a command message and
   * return its result message.
   *
   * @since 1.0.0
   *
   * @param CommandMessage $command the command to dispatch
   *
   * @return ResultMessage the result of the command
   */
  public function dispatch(CommandMessage $command): ResultMessage;
  // #endregion
}
