<?php

declare(strict_types=1);

namespace Auth\Application\Port\Inbound;

use Auth\Application\UseCase\Command\Logout\LogoutCommand;
use Auth\Application\UseCase\Command\Logout\LogoutResult;

/**
 * Interface LogoutUseCasePort
 *
 * Inbound port for user logout use case.
 *
 * @category Port
 * @package Auth\Application\Port\Inbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface LogoutUseCasePort
{
  //#region Methods
  /**
   * Method execute
   *
   * Logout a user and revoke their tokens.
   *
   * @access public
   * @since 1.0.0
   *
   * @param LogoutCommand $command The logout command.
   *
   * @return LogoutResult The logout result.
   */
  public function execute(LogoutCommand $command): LogoutResult;
  //#endregion
}
