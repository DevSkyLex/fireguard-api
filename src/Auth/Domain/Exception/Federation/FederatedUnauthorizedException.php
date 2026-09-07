<?php

declare(strict_types=1);

namespace Auth\Domain\Exception\Federation;

/**
 * Signals that the resolved Fireguard account is unavailable.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FederatedUnauthorizedException extends FederatedAuthException
{
}
