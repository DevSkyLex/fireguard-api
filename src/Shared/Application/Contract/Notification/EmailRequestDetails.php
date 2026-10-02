<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Notification;

use Shared\Application\Contract\GeoIp\IpLocation;
use Shared\Application\Contract\Http\RequestOrigin;

/**
 * Ephemeral email context; the raw IP is deliberately excluded.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EmailRequestDetails
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param ?string $browser parsed browser label
   * @param ?string $operatingSystem parsed operating system label
   * @param ?IpLocation $location approximate location at the triggering event
   * @param string $locale email locale
   */
  public function __construct(
    public ?string $browser = null,
    public ?string $operatingSystem = null,
    public ?IpLocation $location = null,
    public string $locale = 'en',
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param ?RequestOrigin $origin transient request details
   * @param ?IpLocation $location optional local lookup result
   *
   * @return self email-safe context without the request IP
   */
  public static function fromOrigin(?RequestOrigin $origin, ?IpLocation $location): self
  {
    if (null === $origin) {
      return new self(location: $location);
    }

    return new self($origin->browser, $origin->operatingSystem, $location, $origin->locale);
  }
  // #endregion
}
