<?php

declare(strict_types=1);

namespace Shared\Application\Handler;

use Shared\Application\Message\CommandMessage;
use Shared\Application\Message\ResultMessage;

/**
 * Handler CommandHandler
 *
 * Handler for command messages.
 *
 * @category Handler
 * @package Shared\Application\Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CommandHandler
{
  //#region Methods
  /**
   * Method __invoke
   * @method __invoke(CommandMessage $command): ?ResultMessage
   *
   * Invoke the command handler.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CommandMessage $command The command to handle.
   *
   * @return ?ResultMessage The result of the command.
   */
  public function __invoke(CommandMessage $command): ?ResultMessage;
  //#endregion
}
