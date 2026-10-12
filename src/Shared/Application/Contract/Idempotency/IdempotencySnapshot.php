<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Idempotency;

use InvalidArgumentException;

use function is_string;

/**
 * Class IdempotencySnapshot
 *
 * Stores opaque versioned application-result bytes before the owning commit.
 * Presentation reconstructs the eventual response; this is never a response-event cache.
 *
 * @category Contract
 */
final readonly class IdempotencySnapshot
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param int $statusCode result status hint
   * @param string $body opaque application-result encoding
   * @param array<string, string> $headers scalar result metadata
   * @param bool $secret whether the result requires the five-minute encrypted replay window
   * @param array<string, string> $proof verified non-secret owner, revision and permission metadata
   *
   * @return void
   */
  public function __construct(
    public int $statusCode,
    public string $body,
    public array $headers = [],
    public bool $secret = false,
    public array $proof = [],
  ) {
    if ($statusCode < 100 || $statusCode > 599) {
      throw new InvalidArgumentException('Invalid result status.');
    }
    foreach ($headers as $name => $value) {
      self::assertText($name);
      self::assertText($value);
    }
    foreach ($proof as $name => $value) {
      self::assertText($name);
      self::assertText($value);
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method assertText
   *
   * Validates decoded metadata at runtime without trusting caller PHPDoc types.
   *
   * @access private
   *
   * @param mixed $value untrusted metadata value
   *
   * @return void
   */
  private static function assertText(mixed $value): void
  {
    if (!is_string($value)) {
      throw new InvalidArgumentException('Result metadata must be scalar text.');
    }
  }
  // #endregion
}
