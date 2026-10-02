<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Http;

/**
 * Trusted request IP and fixed device labels, kept only during the request.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestOrigin
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param ?string $ipAddress client IP resolved through the trusted proxy configuration
   * @param ?string $browser parsed browser label, never the raw User-Agent
   * @param ?string $operatingSystem parsed operating system label
   * @param string $locale request locale
   */
  public function __construct(
    public ?string $ipAddress,
    public ?string $browser = null,
    public ?string $operatingSystem = null,
    public string $locale = 'en',
  ) {
  }
  // #endregion
}
