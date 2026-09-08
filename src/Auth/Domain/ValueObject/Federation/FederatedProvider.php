<?php

declare(strict_types=1);

namespace Auth\Domain\ValueObject\Federation;

/**
 * Enum FederatedProvider.
 *
 * Providers accepted by Fireguard's external sign-in boundary.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum FederatedProvider: string
{
  case GOOGLE = 'google';
  case MICROSOFT = 'microsoft';
}
