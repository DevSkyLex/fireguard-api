<?php

declare(strict_types=1);

namespace Shared\Application\Message;

/**
 * Interface QueryHandler.
 *
 * Marker interface for CQRS query handlers.
 * Handlers implement this interface and define their own
 * __invoke(ConcreteQuery $q): ConcreteResult signature.
 *
 * @category Message
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface QueryHandler
{
}
