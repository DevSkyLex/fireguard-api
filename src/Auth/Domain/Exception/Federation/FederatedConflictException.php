<?php

declare(strict_types=1);

namespace Auth\Domain\Exception\Federation;

/**
 * Signals a federated sign-in method conflict.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FederatedConflictException extends FederatedAuthException
{
}
