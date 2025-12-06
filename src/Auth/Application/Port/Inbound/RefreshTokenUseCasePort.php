<?php

declare(strict_types=1);

namespace Auth\Application\Port\Inbound;

use Auth\Application\UseCase\Query\RefreshToken\RefreshTokenQuery;
use Auth\Application\UseCase\Query\RefreshToken\RefreshTokenResult;

/**
 * Interface RefreshTokenUseCasePort
 *
 * Inbound port for token refresh use case.
 *
 * @category Port
 * @package Auth\Application\Port\Inbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface RefreshTokenUseCasePort
{
  //#region Methods
  /**
   * Method execute
   *
   * Refresh an access token using a refresh token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RefreshTokenQuery $query The refresh token query.
   *
   * @return RefreshTokenResult The refresh result.
   */
  public function execute(RefreshTokenQuery $query): RefreshTokenResult;
  //#endregion
}
