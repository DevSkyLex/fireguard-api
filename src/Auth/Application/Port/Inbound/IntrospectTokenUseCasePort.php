<?php

declare(strict_types=1);

namespace Auth\Application\Port\Inbound;

use Auth\Application\UseCase\Query\IntrospectToken\IntrospectTokenQuery;
use Auth\Application\UseCase\Query\IntrospectToken\IntrospectTokenResult;

/**
 * Interface IntrospectTokenUseCasePort
 *
 * Inbound port for token introspection use case (RFC 7662).
 *
 * @category Port
 * @package Auth\Application\Port\Inbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface IntrospectTokenUseCasePort
{
  //#region Methods
  /**
   * Method execute
   *
   * Introspect a token to determine its state.
   *
   * @access public
   * @since 1.0.0
   *
   * @param IntrospectTokenQuery $query The introspection query.
   *
   * @return IntrospectTokenResult The introspection result.
   */
  public function execute(IntrospectTokenQuery $query): IntrospectTokenResult;
  //#endregion
}
