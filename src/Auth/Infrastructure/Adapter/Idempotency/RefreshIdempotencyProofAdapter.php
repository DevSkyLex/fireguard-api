<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionAuthorizationProofPort};
use Auth\Application\UseCase\Command\Session\RefreshToken\{RefreshTokenCommand, RefreshTokenResult};
use Session\Application\Port\Inbound\SessionReplayProofPort;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\SensitiveIdempotencyProofPort;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function max;
use function time;

/**
 * Class RefreshIdempotencyProofAdapter
 *
 * Binds a receipt to one parent→daughter session rotation. Only that receipt accepts either verified input pair while its daughter remains current and live.
 *
 * @category Adapter
 */
final readonly class RefreshIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  // #region Constants
  /**
   * Constant INVALID_TOKEN_MESSAGE
   *
   * Preserves the neutral refresh denial across parent, daughter and current account checks.
   */
  private const string INVALID_TOKEN_MESSAGE = 'Invalid or expired refresh token';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param JwtTokenServicePort $tokens authenticated encrypted refresh decoding
   * @param UserSecurityRevisionPort $users current owner state
   * @param SessionReplayProofPort $sessions fresh exact pair ownership
   *
   * @return void
   */
  public function __construct(private JwtTokenServicePort $tokens, private UserSecurityRevisionPort $users, private SessionReplayProofPort $sessions, private SessionAuthorizationProofPort $grants)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function supports(string $commandClass): bool
  {
    return RefreshTokenCommand::class === $commandClass;
  }

  /**
   * {@inheritDoc}
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    if (!$message instanceof RefreshTokenCommand) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $parent = $this->tokens->decodeRefreshToken($message->refreshToken);
    if (null === $parent || '' === $parent['user_id'] || $parent['expires_at'] <= time()) {
      throw new UnauthorizedHttpException('Bearer', self::INVALID_TOKEN_MESSAGE);
    }
    $current = $this->users->current($parent['user_id']);
    if (null === $current || !$current->active) {
      throw new UnauthorizedHttpException('Bearer', self::INVALID_TOKEN_MESSAGE);
    }
    if (null !== $storedProof) {
      $this->assertReplayRotation($current->userId, $current->securityRevision, $parent, $storedProof);
    }

    return new SensitiveIdempotencyAuthorization($current->userId, [
      // Exact pair membership is receipt-authenticated above. The cookie can already contain the daughter after a lost response body.
      'operation' => 'interactive_refresh',
      'user_id' => $current->userId,
    ], ['user_id' => $current->userId, 'security_revision_before' => (string) $current->securityRevision, 'parent_access_id' => $parent['access_token_id'], 'parent_refresh_id' => $parent['refresh_token_id']]);
  }

  /**
   * {@inheritDoc}
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    if (!$message instanceof RefreshTokenCommand || !$result instanceof RefreshTokenResult) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $authorization = $this->authorize($message, null);
    if (!$result->success || null === $result->userId || null === $result->refreshToken || null === $result->accessTokenId || null === $result->refreshTokenId) {
      return $authorization->receiptProof + ['success' => '0'];
    }
    $daughter = $this->tokens->decodeRefreshToken($result->refreshToken);
    $current = $this->users->current($result->userId);
    $sessionId = $this->sessions->currentSessionId($result->userId, $result->accessTokenId, $result->refreshTokenId);
    if (null === $daughter || null === $current || !$current->active || null === $sessionId || $daughter['user_id'] !== $result->userId || $daughter['access_token_id'] !== $result->accessTokenId || $daughter['refresh_token_id'] !== $result->refreshTokenId) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $authorization->receiptProof + [
      'success' => '1',
      'security_revision' => (string) $current->securityRevision,
      'security_revision_after' => (string) $current->securityRevision,
      'authorization_fingerprint' => $this->grants->issuedFingerprint($current->userId, $result->accessToken ?? throw new IdempotencyDeniedException('result_unavailable')),
      'session_id' => $sessionId,
      'daughter_access_id' => $result->accessTokenId,
      'daughter_refresh_id' => $result->refreshTokenId,
      'refresh_expires_at' => (string) $daughter['expires_at'],
      'access_expires_at' => (string) (time() + $result->expiresIn),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    if (!$result instanceof RefreshTokenResult) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return new RefreshTokenResult($result->success, $result->userId, $result->accessToken, $result->refreshToken, $result->tokenType, max(0, (int) ($proof->metadata['access_expires_at'] ?? 0) - time()), $result->scopes, $result->errorMessage, $result->accessTokenId, $result->refreshTokenId, $result->rememberMe);
  }

  /**
   * Method assertReplayRotation
   *
   * Requires receipt-authenticated parent or daughter membership and the same live daughter session.
   *
   * @access private
   *
   * @param string $userId current account owner
   * @param int $securityRevision current account revision
   * @param array{refresh_token_id:string,access_token_id:string,user_id:string,scopes:array<string>,expires_at:int,remember_me?:bool} $parent verified incoming pair
   * @param IdempotencyProof $storedProof authenticated receipt envelope
   *
   * @return void
   */
  private function assertReplayRotation(string $userId, int $securityRevision, array $parent, IdempotencyProof $storedProof): void
  {
    $proof = $storedProof->metadata;
    if (($proof['authorization_fingerprint'] ?? null) !== $this->grants->currentFingerprint($userId)) {
      throw new UnauthorizedHttpException('Bearer', self::INVALID_TOKEN_MESSAGE);
    }
    $parentMatches = ($proof['parent_refresh_id'] ?? null) === $parent['refresh_token_id'] && ($proof['parent_access_id'] ?? null) === $parent['access_token_id'];
    $daughterMatches = ($proof['daughter_refresh_id'] ?? null) === $parent['refresh_token_id'] && ($proof['daughter_access_id'] ?? null) === $parent['access_token_id'];
    if ((!$parentMatches && !$daughterMatches) || ($proof['user_id'] ?? null) !== $userId || ($proof['security_revision'] ?? null) !== (string) $securityRevision || (int) ($proof['refresh_expires_at'] ?? 0) <= time()) {
      throw new UnauthorizedHttpException('Bearer', self::INVALID_TOKEN_MESSAGE);
    }
    $sessionId = $this->sessions->currentSessionId($userId, $proof['daughter_access_id'] ?? '', $proof['daughter_refresh_id'] ?? '');
    if (null === $sessionId || ($proof['session_id'] ?? null) !== $sessionId) {
      throw new UnauthorizedHttpException('Bearer', self::INVALID_TOKEN_MESSAGE);
    }
  }
  // #endregion
}
