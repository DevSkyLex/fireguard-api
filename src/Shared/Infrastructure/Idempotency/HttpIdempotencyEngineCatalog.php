<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use InvalidArgumentException;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyPort;

/**
 * Class HttpIdempotencyEngineCatalog
 *
 * Selects only an explicitly declared owning database and never falls back to the default entity manager.
 *
 * @category Adapter
 */
final readonly class HttpIdempotencyEngineCatalog
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param HttpIdempotencyPort $main the explicitly main-bound durable engine
   * @param HttpIdempotencyPort $auth the explicitly auth-bound durable engine
   *
   * @return void
   */
  public function __construct(private HttpIdempotencyPort $main, private HttpIdempotencyPort $auth)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method forDatabase
   *
   * @access public
   *
   * @param string $database the operation's explicit database ownership
   *
   * @return HttpIdempotencyPort the matching owning engine
   */
  public function forDatabase(string $database): HttpIdempotencyPort
  {
    return match ($database) {
      'main' => $this->main,
      'auth' => $this->auth,
      default => throw new InvalidArgumentException('HTTP idempotency database ownership is undeclared.'),
    };
  }
  // #endregion
}
