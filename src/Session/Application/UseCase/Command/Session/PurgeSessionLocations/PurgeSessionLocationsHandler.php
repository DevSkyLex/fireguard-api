<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\PurgeSessionLocations;

use Session\Application\Port\Outbound\SessionRepositoryPort;
use Shared\Application\Message\CommandHandler;

/**
 * Erases session geography through the auth persistence port.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PurgeSessionLocationsHandler implements CommandHandler
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param SessionRepositoryPort $sessions auth-side session storage
   */
  public function __construct(private SessionRepositoryPort $sessions)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param PurgeSessionLocationsCommand $command erasure scope
   *
   * @return PurgeSessionLocationsResult count of updated rows
   */
  public function __invoke(PurgeSessionLocationsCommand $command): PurgeSessionLocationsResult
  {
    return new PurgeSessionLocationsResult($this->sessions->purgeLocations($command->userId));
  }
  // #endregion
}
