<?php

declare(strict_types=1);

namespace Shared\Application\Port\Inbound;

use Shared\Application\Message\CommandMessage;
use Shared\Application\Message\ResultMessage;

/**
 * Port CommandBusPort
 *
 * Port used to send commands
 * to the application.
 *
 * @category Inbound Port
 * @package Shared\Application\Port\Inbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CommandBusPort
{
  //#region Methods
  /**
   * Method dispatch
   * @method dispatch(CommandMessage $command): ResultMessage
   *
   * Dispatch a command message and
   * return its result message.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CommandMessage $command The command to dispatch.
   *
   * @return ResultMessage The result of the command.
   */
  public function dispatch(CommandMessage $command): ResultMessage;
  //#endregion
}
