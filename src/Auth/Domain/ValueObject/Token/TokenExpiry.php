<?php

declare(strict_types=1);

namespace Auth\Domain\ValueObject\Token;

use Shared\Domain\Trait\HasTokenExpiry;

/**
 * ValueObject TokenExpiry.
 *
 * Keeps the module-owned expiry type while sharing its absolute-deadline mechanics.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenExpiry
{
  // #region Traits
  use HasTokenExpiry;
  // #endregion
}
