<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Port\Outbound\{AuthenticationPolicyPort, JwtTokenServicePort, SessionAuthorizationProofPort};
use Auth\Application\UseCase\Command\Session\Login\{LoginCommand, LoginResult};
use Session\Application\Port\Inbound\SessionReplayProofPort;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{IdempotencyFingerprintPort, SensitiveIdempotencyProofPort};
use Shared\Application\Port\Outbound\RateLimiterPort;
use Symfony\Component\HttpKernel\Exception\{TooManyRequestsHttpException, UnauthorizedHttpException};
use User\Application\Port\Inbound\{CredentialLifecycleProofPort, UserCredentialProofPort, UserSecurityRevisionPort};

use function hash;
use function max;
use function sprintf;
use function strtolower;
use function substr;
use function time;
use function trim;

/**
 * Class LoginIdempotencyProofAdapter
 *
 * Rechecks current credentials and account revision before any encrypted login response is replayed.
 *
 * @category Adapter
 */
final readonly class LoginIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  // #region Constants
  /**
   * Constant INVALID_CREDENTIALS_MESSAGE
   *
   * Keeps credential and replay denials indistinguishable.
   */
  private const string INVALID_CREDENTIALS_MESSAGE = 'Invalid credentials';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param UserCredentialProofPort $credentials fresh credential proof
   * @param UserSecurityRevisionPort $revisions current account revision
   * @param AuthenticationPolicyPort $policy current authentication choices
   * @param JwtTokenServicePort $tokens verified token lifetimes
   *
   * @return void
   */
  public function __construct(private UserCredentialProofPort $credentials, private UserSecurityRevisionPort $revisions, private AuthenticationPolicyPort $policy, private JwtTokenServicePort $tokens, private RateLimiterPort $rateLimiter, private SessionReplayProofPort $sessions, private IdempotencyFingerprintPort $fingerprints, private SessionAuthorizationProofPort $grants, private ?CredentialLifecycleProofPort $lifecycle = null)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function supports(string $commandClass): bool
  {
    return LoginCommand::class === $commandClass;
  }

  /**
   * {@inheritDoc}
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    if (!$message instanceof LoginCommand) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if (null === $storedProof) {
      $limit = $this->rateLimiter->consume(sprintf('login_%s_%s', substr(hash('sha256', strtolower($message->email)), 0, 16), substr(hash('sha256', $message->ipAddress ?? 'unknown'), 0, 16)));
      if (!$limit->accepted) {
        throw new TooManyRequestsHttpException($limit->retryAfter, 'Too many login attempts. Please try again later.');
      }
    }
    $current = $this->credentials->verify($message->email, $message->password, $this->policy->emailVerificationRequired());
    $failedReceipt = null !== $storedProof && '1' === ($storedProof->metadata['failed_login'] ?? null);
    if (null !== $storedProof && !$failedReceipt && (null === $current || !$current->active || ($storedProof->metadata['user_id'] ?? null) !== $current->userId || ($storedProof->metadata['security_revision'] ?? null) !== (string) $current->securityRevision)) {
      throw new UnauthorizedHttpException('Bearer', self::INVALID_CREDENTIALS_MESSAGE);
    }
    if (null !== $storedProof && !$failedReceipt && null !== $current && ($storedProof->metadata['authorization_fingerprint'] ?? null) !== $this->grants->currentFingerprint($current->userId)) {
      throw new UnauthorizedHttpException('Bearer', self::INVALID_CREDENTIALS_MESSAGE);
    }
    if (null !== $storedProof && null !== $current && isset($storedProof->metadata['session_id'])) {
      $sessionId = $this->sessions->currentSessionId($current->userId, $storedProof->metadata['access_token_id'] ?? '', $storedProof->metadata['refresh_token_id'] ?? '');
      if ($sessionId !== $storedProof->metadata['session_id']) {
        throw new UnauthorizedHttpException('Bearer', self::INVALID_CREDENTIALS_MESSAGE);
      }
    }

    return new SensitiveIdempotencyAuthorization('login:' . $this->fingerprints->digest('login.principal', strtolower(trim($message->email))), [
      'identifier' => strtolower(trim($message->email)),
      'password' => $message->password,
      'remember_me' => $message->rememberMe,
      'trusted_device' => $message->trustedDeviceToken,
      'email_verification_required' => $this->policy->emailVerificationRequired(),
      'mfa_required' => $this->policy->mfaRequired(),
      'trusted_device_bypass' => $this->policy->trustedDeviceMfaBypassEnabled(),
    ], null === $current ? [] : ['user_id' => $current->userId, 'security_revision_before' => (string) $current->securityRevision]);
  }

  /**
   * {@inheritDoc}
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    if (!$message instanceof LoginCommand || !$result instanceof LoginResult) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if (!$result->authenticated && true !== $result->mfaRequired && null === $result->accessToken && null === $result->refreshToken && null === $result->mfaToken) {
      $owner = $this->lifecycle?->byEmail($message->email);

      return ['failed_login' => '1'] + (null === $owner ? [] : ['user_id' => $owner->userId]);
    }
    $current = null === $result->userId ? $this->credentials->verify($message->email, $message->password, $this->policy->emailVerificationRequired()) : $this->revisions->current($result->userId);
    if (null === $current || !$current->active) {
      throw new UnauthorizedHttpException('Bearer', self::INVALID_CREDENTIALS_MESSAGE);
    }
    $proof = ['user_id' => $current->userId, 'security_revision' => (string) $current->securityRevision, 'security_revision_after' => (string) $current->securityRevision];
    $proof['authorization_fingerprint'] = null === $result->accessToken ? $this->grants->currentFingerprint($current->userId) : $this->grants->issuedFingerprint($current->userId, $result->accessToken);
    if (null !== $result->refreshToken) {
      $refresh = $this->tokens->decodeRefreshToken($result->refreshToken);
      if (null === $refresh) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $proof['refresh_expires_at'] = (string) $refresh['expires_at'];
      $sessionId = $this->sessions->currentSessionId($current->userId, $refresh['access_token_id'], $refresh['refresh_token_id']);
      if (null === $sessionId) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $proof['session_id'] = $sessionId;
      $proof['access_token_id'] = $refresh['access_token_id'];
      $proof['refresh_token_id'] = $refresh['refresh_token_id'];
    }
    $proof['access_expires_at'] = (string) (time() + $result->expiresIn);

    return $proof;
  }

  /**
   * {@inheritDoc}
   */
  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    if (!$result instanceof LoginResult) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return new LoginResult(
      authenticated: $result->authenticated,
      userId: $result->userId,
      email: $result->email,
      accessToken: $result->accessToken,
      refreshToken: $result->refreshToken,
      tokenType: $result->tokenType,
      expiresIn: max(0, (int) ($proof->metadata['access_expires_at'] ?? 0) - time()),
      scopes: $result->scopes,
      mfaRequired: $result->mfaRequired,
      mfaToken: $result->mfaToken,
      challengeToken: $result->challengeToken,
      mfaMethod: $result->mfaMethod,
      mfaDestination: $result->mfaDestination,
      errorMessage: $result->errorMessage,
      errorCode: $result->errorCode,
      rateLimitRetryAfter: $result->rateLimitRetryAfter,
    );
  }
  // #endregion
}
