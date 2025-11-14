<?php

namespace Shared\Application\Handler;

use Shared\Application\Message\QueryMessage;
use Shared\Application\Message\ResultMessage;

/**
 * Handler QueryHandler
 *
 * Handler for query messages.
 *
 * @category Handler
 * @package Shared\Application\Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface QueryHandler
{
  //#region Methods
  /**
   * Method __invoke
   *
   * Invoke the query handler.
   *
   * @access public
   * @since 1.0.0
   *
   * @param QueryMessage $query The query to handle.
   *
   * @return ResultMessage The result of the query.
   */
  public function __invoke(QueryMessage $query): ResultMessage;
  //#endregion
}
