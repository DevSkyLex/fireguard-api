<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Token;

/**
 * ValueObject AccessTokenStatus.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AccessTokenStatus
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<string> $scopes the token scopes
   * @param bool $revoked whether the token is revoked
   * @param bool $expired whether the token is expired
   */
  public function __construct(
    public array $scopes,
    public bool $revoked,
    public bool $expired,
  ) {
  }
  // #endregion
}
