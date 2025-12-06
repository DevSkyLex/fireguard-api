<?php

declare(strict_types=1);

namespace Auth\Application\Port\Inbound;

use Auth\Application\UseCase\Command\Login\LoginCommand;
use Auth\Application\UseCase\Command\Login\LoginResult;

/**
 * Interface LoginUseCasePort
 *
 * Inbound port for user authentication use case.
 *
 * @category Port
 * @package Auth\Application\Port\Inbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface LoginUseCasePort
{
  //#region Methods
  /**
   * Method execute
   *
   * Authenticate a user with email and password.
   *
   * @access public
   * @since 1.0.0
   *
   * @param LoginCommand $command The login command.
   *
   * @return LoginResult The login result.
   */
  public function execute(LoginCommand $command): LoginResult;
  //#endregion
}
