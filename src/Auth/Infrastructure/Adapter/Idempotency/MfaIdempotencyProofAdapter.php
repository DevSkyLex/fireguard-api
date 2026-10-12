<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Port\Outbound\{AuthenticationPolicyPort, JwtTokenServicePort, SessionAuthorizationProofPort};
use Auth\Application\UseCase\Command\Mfa\MfaVerify\{MfaVerifyCommand, MfaVerifyResult};
use Otp\Application\Contract\Challenge\OtpPurpose;
use Otp\Application\Port\Inbound\Challenge\ChallengeReplayProofPort;
use Session\Application\Port\Inbound\SessionReplayProofPort;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\SensitiveIdempotencyProofPort;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use User\Application\Port\Inbound\{SignInEligibilityPort, UserSecurityRevisionPort};

use function is_string;
use function max;
use function time;

/**
 * Binds successful and failed MFA receipts to a currently verified preauthentication token and its exact consumed challenge.
 *
 * @category Adapter
 */
final readonly class MfaIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  /**
   * @param JwtTokenServicePort $tokens authenticated token decoding
   * @param UserSecurityRevisionPort $users current account security state
   * @param ChallengeReplayProofPort $challenges fresh owner challenge facts
   * @param SessionReplayProofPort $sessions current issued pair ownership
   * @param AuthenticationPolicyPort $policy current sign-in policy
   */
  public function __construct(private JwtTokenServicePort $tokens, private UserSecurityRevisionPort $users, private ChallengeReplayProofPort $challenges, private SessionReplayProofPort $sessions, private AuthenticationPolicyPort $policy, private SignInEligibilityPort $accounts, private SessionAuthorizationProofPort $grants)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function supports(string $commandClass): bool
  {
    return MfaVerifyCommand::class === $commandClass;
  }

  /**
   * {@inheritDoc}
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    if (!$message instanceof MfaVerifyCommand) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $claims = $this->tokens->decodePreAuthToken($message->preAuthToken);
    $userId = $claims['sub'] ?? null;
    $token = $claims['challenge_token'] ?? null;
    if (!is_string($userId) || '' === $userId || !is_string($token) || '' === $token) {
      throw new UnauthorizedHttpException('Bearer', 'Invalid or expired MFA challenge.');
    }
    $user = $this->users->current($userId);
    $challenge = $this->challenges->current($token, $userId, OtpPurpose::LOGIN);
    if (null === $user || !$this->accounts->isEligible($userId, $this->policy->emailVerificationRequired()) || null === $challenge || $challenge->expiresAt->getTimestamp() <= time()) {
      throw new UnauthorizedHttpException('Bearer', 'Invalid or expired MFA challenge.');
    }
    if (null !== $storedProof) {
      $proof = $storedProof->metadata;
      if (($proof['user_id'] ?? null) !== $userId || ($proof['challenge_id'] ?? null) !== $challenge->challengeId || ($proof['security_revision'] ?? null) !== (string) $user->securityRevision) {
        throw new UnauthorizedHttpException('Bearer', 'Invalid or expired MFA challenge.');
      }
      if ('1' === ($proof['success'] ?? null)) {
        if (($proof['authorization_fingerprint'] ?? null) !== $this->grants->currentFingerprint($userId)) {
          throw new UnauthorizedHttpException('Bearer', 'Invalid or expired MFA challenge.');
        }
        if (null === $challenge->verifiedAt || ($proof['challenge_verified_at'] ?? null) !== $challenge->verifiedAt->format('U.u') || ($proof['session_id'] ?? null) !== $this->sessions->currentSessionId($userId, $proof['access_token_id'] ?? '', $proof['refresh_token_id'] ?? '') || (int) ($proof['refresh_expires_at'] ?? 0) <= time()) {
          throw new UnauthorizedHttpException('Bearer', 'Invalid or expired MFA challenge.');
        }
      }
    }

    return new SensitiveIdempotencyAuthorization($userId, ['preAuthToken' => $message->preAuthToken, 'code' => $message->code, 'method' => $message->method], ['user_id' => $userId, 'challenge_id' => $challenge->challengeId, 'security_revision_before' => (string) $user->securityRevision]);
  }

  /**
   * {@inheritDoc}
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    if (!$message instanceof MfaVerifyCommand || !$result instanceof MfaVerifyResult) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $authorization = $this->authorize($message, null);
    $user = $this->users->current($authorization->principalId) ?? throw new IdempotencyDeniedException('result_unavailable');
    $claims = $this->tokens->decodePreAuthToken($message->preAuthToken);
    $token = $claims['challenge_token'] ?? null;
    $challenge = is_string($token) ? $this->challenges->current($token, $user->userId, OtpPurpose::LOGIN) : null;
    if (null === $challenge) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $proof = ['user_id' => $user->userId, 'security_revision' => (string) $user->securityRevision, 'challenge_id' => $challenge->challengeId, 'success' => $result->success ? '1' : '0', 'attempts' => (string) $challenge->attempts];
    if (!$result->success) {
      return $proof;
    }
    $pair = null === $result->refreshToken ? null : $this->tokens->decodeRefreshToken($result->refreshToken);
    $sessionId = null === $pair ? null : $this->sessions->currentSessionId($user->userId, $pair['access_token_id'], $pair['refresh_token_id']);
    if (null === $pair || null === $sessionId || null === $challenge->verifiedAt) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $proof + ['authorization_fingerprint' => $this->grants->issuedFingerprint($user->userId, $result->accessToken ?? throw new IdempotencyDeniedException('result_unavailable')), 'challenge_verified_at' => $challenge->verifiedAt->format('U.u'), 'session_id' => $sessionId, 'access_token_id' => $pair['access_token_id'], 'refresh_token_id' => $pair['refresh_token_id'], 'access_expires_at' => (string) (time() + $result->expiresIn), 'refresh_expires_at' => (string) $pair['expires_at']];
  }

  /**
   * {@inheritDoc}
   */
  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    if (!$result instanceof MfaVerifyResult) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return new MfaVerifyResult($result->success, $result->attemptsRemaining, $result->error, $result->accessToken, $result->refreshToken, $result->tokenType, $result->success ? max(0, (int) ($proof->metadata['access_expires_at'] ?? 0) - time()) : $result->expiresIn, $result->scopes, $result->rememberMe);
  }
}
