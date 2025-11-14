<?php

declare(strict_types=1);

namespace Shared\Application\Port\Inbound;

use Shared\Application\Message\QueryMessage;
use Shared\Application\Message\ResultMessage;

/**
 * Port QueryBusPort
 *
 * Port used to send queries
 * to the application.
 *
 * @category Inbound Port
 * @package Shared\Application\Port\Inbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface QueryBusPort
{
  //#region Methods
  /**
   * Method ask
   * @method ask(QueryMessage $query): ResultMessage
   *
   * Ask a query message handler and
   * return its result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param QueryMessage $query The query to ask.
   *
   * @return ResultMessage The result of the query.
   */
  public function ask(QueryMessage $query): ResultMessage;
  //#endregion
}
