<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Command\Publication\RequestPublication;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase RequestPublicationCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPublicationCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the RequestPublicationCommand class.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $missionId the mission id value
   * @param int $missionRevision the mission revision value
   */
  public function __construct(
    public string $userId,
    public string $missionId,
    public int $missionRevision,
  ) {
  }
}
