<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\CreateSession;

use Session\Domain\ValueObject\SessionMetadata;
use Shared\Domain\ValueObject\IpAddress;
use Shared\Domain\ValueObject\UserAgent;

/**
 * Command CreateSessionCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateSessionCommand
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param IpAddress $ipAddress the client IP address
   * @param UserAgent $userAgent the client user agent
   * @param SessionMetadata|null $metadata optional session metadata
   */
  public function __construct(
    public string $userId,
    public IpAddress $ipAddress,
    public UserAgent $userAgent,
    public ?SessionMetadata $metadata = null,
  ) {
  }
  // #endregion
}
