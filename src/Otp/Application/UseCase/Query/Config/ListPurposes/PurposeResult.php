<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\Config\ListPurposes;

/**
 * PurposeResult.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PurposeResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param string $value the purpose identifier
   * @param string $label the human-readable label
   * @param int $ttlSeconds default TTL in seconds
   * @param int $maxAttempts default max attempts
   */
  public function __construct(
    public string $value,
    public string $label,
    public int $ttlSeconds,
    public int $maxAttempts,
  ) {
  }
  // #endregion
}
