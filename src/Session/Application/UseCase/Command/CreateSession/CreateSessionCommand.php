<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\CreateSession;

use Session\Domain\ValueObject\SessionMetadata;
use Shared\Domain\ValueObject\IpAddress;
use Shared\Domain\ValueObject\UserAgent;

/**
 * Command CreateSessionCommand
 * @final
 *
 * Command to create a new session.
 *
 * @category Command
 * @package Session\Application\UseCase\Command\CreateSession
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateSessionCommand
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   * @param IpAddress $ipAddress The client IP address.
   * @param UserAgent $userAgent The client user agent.
   * @param SessionMetadata|null $metadata Optional session metadata.
   */
  public function __construct(
    public string $userId,
    public IpAddress $ipAddress,
    public UserAgent $userAgent,
    public ?SessionMetadata $metadata = null,
  ) {
  }
  //#endregion
}
