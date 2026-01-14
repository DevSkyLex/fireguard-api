<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\Session\GetSession;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Result GetSessionResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetSessionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $sessionId the session ID
   * @param string $userId the user ID
   * @param string $ipAddress the IP address
   * @param string $userAgent the user agent
   * @param string|null $deviceType the device type
   * @param string|null $browser the browser name
   * @param string|null $operatingSystem the OS name
   * @param string|null $country the country code
   * @param string|null $city the city name
   * @param bool $rememberMe whether the session is persistent
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $lastActivityAt the last activity timestamp
   * @param bool $isRevoked whether the session is revoked
   */
  public function __construct(
    public string $sessionId,
    public string $userId,
    public string $ipAddress,
    public string $userAgent,
    public ?string $deviceType,
    public ?string $browser,
    public ?string $operatingSystem,
    public ?string $country,
    public ?string $city,
    public bool $rememberMe,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $lastActivityAt,
    public bool $isRevoked,
  ) {
  }
  // #endregion
}
