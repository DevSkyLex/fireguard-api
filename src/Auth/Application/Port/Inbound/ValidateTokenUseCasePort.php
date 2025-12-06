<?php

declare(strict_types=1);

namespace Auth\Application\Port\Inbound;

use Auth\Application\UseCase\Query\ValidateToken\ValidateTokenQuery;
use Auth\Application\UseCase\Query\ValidateToken\ValidateTokenResult;

/**
 * Interface ValidateTokenUseCasePort
 *
 * Inbound port for token validation use case.
 *
 * @category Port
 * @package Auth\Application\Port\Inbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ValidateTokenUseCasePort
{
  //#region Methods
  /**
   * Method execute
   *
   * Validate a JWT access token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ValidateTokenQuery $query The validation query.
   *
   * @return ValidateTokenResult The validation result.
   */
  public function execute(ValidateTokenQuery $query): ValidateTokenResult;
  //#endregion
}
