<?php

declare(strict_types=1);

namespace Shared\Application\Message;

/**
 * Interface CommandHandler.
 *
 * Marker interface for CQRS command handlers.
 * Handlers implement this interface and define their own
 * __invoke(ConcreteCommand $cmd): ConcreteResult signature.
 *
 * @category Message
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CommandHandler
{
}
