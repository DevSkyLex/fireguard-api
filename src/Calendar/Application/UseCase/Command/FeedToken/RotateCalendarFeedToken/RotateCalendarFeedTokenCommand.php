<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\FeedToken\RotateCalendarFeedToken;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase RotateCalendarFeedTokenCommand.
 *
 * Creates the acting member's personal iCal feed token, revoking any
 * previously active one (create and regenerate are the same operation).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RotateCalendarFeedTokenCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $actorUserId the acting (and owning) user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
  ) {
  }
  // #endregion
}
