<?php

declare(strict_types=1);

namespace Auth\Application\Port\Inbound;

use Auth\Application\UseCase\Command\RevokeToken\RevokeTokenCommand;
use Auth\Application\UseCase\Command\RevokeToken\RevokeTokenResult;

/**
 * Interface RevokeTokenUseCasePort
 *
 * Inbound port for token revocation use case (RFC 7009).
 *
 * @category Port
 * @package Auth\Application\Port\Inbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface RevokeTokenUseCasePort
{
  //#region Methods
  /**
   * Method execute
   *
   * Revoke an access or refresh token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RevokeTokenCommand $command The revoke token command.
   *
   * @return RevokeTokenResult The revocation result.
   */
  public function execute(RevokeTokenCommand $command): RevokeTokenResult;
  //#endregion
}
