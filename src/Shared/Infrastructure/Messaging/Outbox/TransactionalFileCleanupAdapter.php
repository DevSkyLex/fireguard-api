<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Shared\Application\Contract\Storage\FileCleanupRequested;
use Shared\Application\Port\Outbound\{EventDispatcherPort, FileCleanupPort};

/**
 * Class TransactionalFileCleanupAdapter
 *
 * Uses the strict transactional dispatcher so cleanup and owning SQL writes commit together.
 *
 * @category Adapter
 */
final readonly class TransactionalFileCleanupAdapter implements FileCleanupPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Requires the strict dispatcher bound to the same database as the owning mutation.
   *
   * @access public
   *
   * @param EventDispatcherPort $events the explicitly wired transactional dispatcher
   * @param 'main'|'auth' $owningDatabase the database that owns the cleanup receipt
   *
   * @return void
   */
  public function __construct(private EventDispatcherPort $events, private string $owningDatabase = 'main')
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method request
   *
   * Records cleanup while the owning mutation is still uncommitted.
   *
   * @access public
   *
   * @param string $storagePath the obsolete storage key
   *
   * @return void
   */
  public function request(string $storagePath): void
  {
    $this->events->dispatch(new FileCleanupRequested($storagePath, $this->owningDatabase));
  }
  // #endregion
}
