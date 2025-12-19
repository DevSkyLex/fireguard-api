<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\GetSession;

/**
 * Query GetSessionQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetSessionQuery
{
    // #region Constructor
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param string $sessionId the session ID
     */
    public function __construct(
        public string $sessionId,
    ) {
    }
    // #endregion
}
