<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\Session\GetSessionByAccessToken;

/**
 * Result GetSessionByAccessTokenResult.
 *
 * Carries `tracked` alongside `revoked` on purpose: session recording is
 * best-effort at every issuance site, so "no session for this token" and
 * "session found, still active" are different facts and the caller must be
 * able to tell them apart.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetSessionByAccessTokenResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $tracked whether a session row exists for this access token identifier
   * @param bool $revoked whether that session has been revoked; always false when untracked
   * @param string|null $sessionId the session identifier, or null when untracked
   * @param string|null $userId the owning user identifier, or null when untracked
   */
  public function __construct(
    public bool $tracked,
    public bool $revoked,
    public ?string $sessionId = null,
    public ?string $userId = null,
  ) {
  }
  // #endregion
}
