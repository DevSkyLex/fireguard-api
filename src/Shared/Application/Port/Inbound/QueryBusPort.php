<?php

declare(strict_types=1);

namespace Shared\Application\Port\Inbound;

use Shared\Application\Message\QueryMessage;
use Shared\Application\Message\ResultMessage;

/**
 * Port QueryBusPort.
 *
 * Port used to send queries
 * to the application.
 *
 * @category Inbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface QueryBusPort
{
    // #region Methods
    /**
     * Method ask.
     *
     * Ask a query message handler and
     * return its result.
     *
     * @since 1.0.0
     *
     * @param QueryMessage $query the query to ask
     *
     * @return ResultMessage the result of the query
     */
    public function ask(QueryMessage $query): ResultMessage;
    // #endregion
}
