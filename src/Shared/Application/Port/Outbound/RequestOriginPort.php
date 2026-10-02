<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Shared\Application\Contract\Http\RequestOrigin;

/**
 * Supplies transient request information without exposing HTTP framework types.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface RequestOriginPort
{
  // #region Methods
  /**
   * @since 1.0.0
   *
   * @return ?RequestOrigin null outside an HTTP request
   */
  public function current(): ?RequestOrigin;
  // #endregion
}
