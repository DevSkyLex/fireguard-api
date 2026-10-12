<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Port\Outbound\{AuthenticationPolicyPort, JwtTokenServicePort, SessionAuthorizationProofPort};
use Auth\Application\UseCase\Command\EmailOwnership\ConfirmEmailOwnership\ConfirmEmailOwnershipCommand;
use Auth\Application\UseCase\Command\EmailOwnership\StartEmailOwnership\StartEmailOwnershipCommand;
use Auth\Application\UseCase\Command\Mfa\MfaResend\MfaResendCommand;
use Auth\Application\UseCase\Command\PasswordChange\ConfirmPasswordChange\ConfirmPasswordChangeCommand;
use Auth\Application\UseCase\Command\PasswordChange\RequestPasswordChange\RequestPasswordChangeCommand;
use Auth\Application\UseCase\Command\PasswordReset\ConfirmPasswordReset\ConfirmPasswordResetCommand;
use Auth\Application\UseCase\Command\PasswordReset\RequestPasswordReset\RequestPasswordResetCommand;
use Auth\Application\UseCase\Command\PasswordReset\ResendPasswordReset\ResendPasswordResetCommand;
use Auth\Application\UseCase\Command\PasswordSetup\ConfirmPasswordSetup\ConfirmPasswordSetupCommand;
use Auth\Application\UseCase\Command\PasswordSetup\RequestPasswordSetup\RequestPasswordSetupCommand;
use Auth\Application\UseCase\Command\Registration\ConfirmRegistration\{ConfirmRegistrationCommand, ConfirmRegistrationResult};
use Auth\Application\UseCase\Command\Registration\RegisterUser\RegisterUserCommand;
use Auth\Application\UseCase\Command\Registration\ResendRegistration\ResendRegistrationCommand;
use Otp\Application\Contract\Challenge\{ChallengeInfo, ChallengeLifecycleProof, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\ChallengeLifecycleProofPort;
use Session\Application\Port\Inbound\SessionReplayProofPort;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyPlatformAuthorityPort, HttpIdempotencyPrincipalPort, SensitiveIdempotencyProofPort};
use User\Application\Contract\User\CredentialLifecycleProof;
use User\Application\Port\Inbound\CredentialLifecycleProofPort;

use function get_object_vars;
use function hash;
use function in_array;
use function is_string;
use function max;
use function strtolower;
use function time;
use function trim;

/**
 * Class CredentialLifecycleIdempotencyProofAdapter
 *
 * Authorizes credential lifecycle receipts against fresh owner facts before secret result decryption.
 * Original passwords and OTPs enter only the canonical request HMAC, never signed receipt metadata.
 *
 * @category Adapter
 */
final readonly class CredentialLifecycleIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  // #region Constructor
  /**
   * @param CredentialLifecycleProofPort $users fresh non-secret account facts and password proof
   * @param ChallengeLifecycleProofPort $challenges fresh challenge ownership and consumption facts
   * @param HttpIdempotencyPrincipalPort $principal verified request actor
   * @param JwtTokenServicePort $tokens verified preauthentication and issued session pairs
   * @param SessionReplayProofPort $sessions fresh issued session revocation anchor
   * @param AuthenticationPolicyPort $policy current authentication policy
   * @param HttpIdempotencyPlatformAuthorityPort $authority fresh current authenticated role requirement
   * @param HttpIdempotencyAccountPort $accounts fresh native account and tenant facts
   * @param SessionAuthorizationProofPort $grants verified issued claims and fresh authorization facts
   */
  public function __construct(private CredentialLifecycleProofPort $users, private ChallengeLifecycleProofPort $challenges, private HttpIdempotencyPrincipalPort $principal, private JwtTokenServicePort $tokens, private SessionReplayProofPort $sessions, private AuthenticationPolicyPort $policy, private HttpIdempotencyPlatformAuthorityPort $authority, private HttpIdempotencyAccountPort $accounts, private SessionAuthorizationProofPort $grants)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function supports(string $commandClass): bool
  {
    return in_array($commandClass, [RegisterUserCommand::class, ResendRegistrationCommand::class, ConfirmRegistrationCommand::class, RequestPasswordResetCommand::class, ResendPasswordResetCommand::class, ConfirmPasswordResetCommand::class, RequestPasswordChangeCommand::class, ConfirmPasswordChangeCommand::class, RequestPasswordSetupCommand::class, ConfirmPasswordSetupCommand::class, StartEmailOwnershipCommand::class, ConfirmEmailOwnershipCommand::class, MfaResendCommand::class], true);
  }

  /**
   * {@inheritDoc}
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    $context = $this->context($message);
    $user = $context['user'];
    $this->assertAuthenticatedContext($context);
    $this->assertChallengeContext($message, $context);
    if (null !== $storedProof) {
      $this->assertStoredProof($message, $context, $storedProof);
    }
    $canonical = get_object_vars($message);
    unset($canonical['ipAddress'], $canonical['userAgent']);

    return new SensitiveIdempotencyAuthorization($context['principal'], $canonical, ['user_id' => $user->userId ?? '', 'security_revision_before' => (string) ($user->securityRevision ?? 0)]);
  }

  /**
   * {@inheritDoc}
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    $context = $this->context($message);
    $values = get_object_vars($result);
    $success = true === ($values['success'] ?? $values['verified'] ?? true);
    $proof = $this->userFacts($context['user']) + $this->challengeFacts($context['challenge'], 'input_') + $this->policyFacts($message) + ['success' => $success ? '1' : '0'];
    $proof += $this->outputChallengeProof($context, $values);
    if ($success && $result instanceof ConfirmRegistrationResult) {
      $user = $context['user'] ?? throw new IdempotencyDeniedException('result_unavailable');
      $proof += $this->registrationResultProof($user, $result);
    }

    return $proof;
  }

  /**
   * {@inheritDoc}
   */
  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    if (!$result instanceof ConfirmRegistrationResult || !$result->success) {
      return $result;
    }

    return new ConfirmRegistrationResult($result->success, $result->message, $result->errorCode, $result->attemptsRemaining, $result->accessToken, $result->refreshToken, $result->tokenType, max(0, (int) ($proof->metadata['access_expires_at'] ?? 0) - time()), $result->scopes);
  }

  /**
   * @param CommandMessage $message supported lifecycle mutation
   *
   * @return array{principal:string,user:?CredentialLifecycleProof,challenge:?ChallengeLifecycleProof,purpose:OtpPurpose,authenticated:bool}
   */
  private function context(CommandMessage $message): array
  {
    if (!$this->supports($message::class)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $purpose = match (true) {
      $message instanceof RegisterUserCommand, $message instanceof ResendRegistrationCommand, $message instanceof ConfirmRegistrationCommand => OtpPurpose::EMAIL_VERIFICATION,
      $message instanceof RequestPasswordResetCommand, $message instanceof ResendPasswordResetCommand, $message instanceof ConfirmPasswordResetCommand => OtpPurpose::PASSWORD_RESET,
      $message instanceof StartEmailOwnershipCommand, $message instanceof ConfirmEmailOwnershipCommand => OtpPurpose::EMAIL_OWNERSHIP,
      $message instanceof MfaResendCommand => OtpPurpose::LOGIN,
      default => OtpPurpose::SENSITIVE_OPERATION,
    };
    if ($message instanceof RegisterUserCommand || $message instanceof RequestPasswordResetCommand) {
      return ['principal' => 'mailbox:' . hash('sha256', strtolower(trim($message->email))), 'user' => $this->users->byEmail($message->email), 'challenge' => null, 'purpose' => $purpose, 'authenticated' => false];
    }
    $values = get_object_vars($message);
    $userId = $values['userId'] ?? null;
    $token = $values['token'] ?? $values['challengeToken'] ?? null;
    if ($message instanceof MfaResendCommand) {
      [$userId, $token] = $this->mfaResendIdentity($message);
    }
    $challenge = is_string($token) ? $this->challenges->current($token) : null;
    $authenticated = is_string($values['userId'] ?? null);
    $ownerId = is_string($userId) ? $userId : $challenge?->userId;
    if (null !== $challenge && is_string($userId) && $challenge->userId !== $userId) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    $tokenValue = is_string($token) ? $token : '';
    $principal = $authenticated || $message instanceof MfaResendCommand ? (string) $ownerId : 'challenge:' . hash('sha256', $tokenValue);

    return ['principal' => $principal, 'user' => null === $ownerId ? null : $this->users->current($ownerId), 'challenge' => $challenge, 'purpose' => $purpose, 'authenticated' => $authenticated];
  }

  /**
   * @param ?CredentialLifecycleProof $user fresh owner state
   *
   * @return array<string, string> signed non-secret account facts
   */
  private function userFacts(?CredentialLifecycleProof $user): array
  {
    return ['user_id' => $user->userId ?? '', 'security_revision' => (string) ($user->securityRevision ?? 0), 'account_status' => $user->status ?? '', 'tenant_id' => null === $user ? '' : ($this->accounts->current($user->userId)->tenantId ?? ''), 'email_hash' => null === $user ? '' : hash('sha256', strtolower($user->email)), 'email_verified' => $user?->emailVerified ? '1' : '0', 'mailbox_verified_at' => $user?->emailOwnershipVerifiedAt?->format('U.u') ?? '', 'password_configured' => $user?->passwordConfigured ? '1' : '0', 'credential_attempts' => (string) ($user->failedLoginAttempts ?? 0)];
  }

  /**
   * @param ?ChallengeLifecycleProof $challenge fresh owner challenge
   * @param string $prefix input consumption or issued output
   *
   * @return array<string, string> signed non-secret challenge facts
   */
  private function challengeFacts(?ChallengeLifecycleProof $challenge, string $prefix): array
  {
    return [$prefix . 'challenge_id' => $challenge->challengeId ?? '', $prefix . 'challenge_user' => $challenge->userId ?? '', $prefix . 'challenge_purpose' => $challenge?->purpose->value ?? '', $prefix . 'challenge_recipient_hash' => null === $challenge ? '' : hash('sha256', strtolower($challenge->recipient)), $prefix . 'challenge_channel' => $challenge->channel ?? '', $prefix . 'challenge_status' => $challenge->status ?? '', $prefix . 'challenge_expires_at' => $challenge?->expiresAt->format('U.u') ?? '', $prefix . 'challenge_verified_at' => $challenge?->verifiedAt?->format('U.u') ?? '', $prefix . 'challenge_attempts' => (string) ($challenge->attempts ?? 0)];
  }

  /**
   * @param CommandMessage $message supported owner operation
   *
   * @return array<string, string> current applicable authentication policy
   */
  private function policyFacts(CommandMessage $message): array
  {
    if (!$message instanceof RegisterUserCommand) {
      return [];
    }

    return ['signup_enabled' => $this->policy->signupEnabled() ? '1' : '0', 'require_email_verification' => $this->policy->emailVerificationRequired() ? '1' : '0'];
  }

  /**
   * Method assertAuthenticatedContext
   *
   * Rechecks the request actor and current native role before receipt facts are compared.
   *
   * @access private
   *
   * @param array{principal:string,user:?CredentialLifecycleProof,challenge:?ChallengeLifecycleProof,purpose:OtpPurpose,authenticated:bool} $context current lifecycle owner
   *
   * @return void
   */
  private function assertAuthenticatedContext(array $context): void
  {
    $user = $context['user'];
    if ($context['authenticated'] && (null === $user || 'active' !== $user->status || $this->principal->current()?->principalId !== $user->userId)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $account = null === $user ? null : $this->accounts->current($user->userId);
    if ($context['authenticated'] && (null === $account || !$this->authority->permitsKey($account, 'ROLE_USER'))) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
  }

  /**
   * Method assertChallengeContext
   *
   * Checks current mailbox and login-challenge ownership independently of a stored receipt.
   *
   * @access private
   *
   * @param CommandMessage $message supported lifecycle mutation
   * @param array{principal:string,user:?CredentialLifecycleProof,challenge:?ChallengeLifecycleProof,purpose:OtpPurpose,authenticated:bool} $context current lifecycle owner
   *
   * @return void
   */
  private function assertChallengeContext(CommandMessage $message, array $context): void
  {
    $user = $context['user'];
    $challenge = $context['challenge'];
    if (null !== $challenge && null !== $user && $challenge->purpose === $context['purpose'] && ('email' === $challenge->channel && strtolower($challenge->recipient) !== strtolower($user->email))) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if ($message instanceof MfaResendCommand && (null === $user || !in_array($user->status, $this->policy->emailVerificationRequired() ? ['active'] : ['active', 'pending_verification'], true) || null === $challenge || OtpPurpose::LOGIN !== $challenge->purpose || $challenge->userId !== $user->userId)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
  }

  /**
   * Method assertStoredProof
   *
   * Applies account facts before credential, output-challenge and issued-session replay guards.
   *
   * @access private
   *
   * @param CommandMessage $message supported lifecycle mutation
   * @param array{principal:string,user:?CredentialLifecycleProof,challenge:?ChallengeLifecycleProof,purpose:OtpPurpose,authenticated:bool} $context current lifecycle owner
   * @param IdempotencyProof $proof authenticated receipt envelope
   *
   * @return void
   */
  private function assertStoredProof(CommandMessage $message, array $context, IdempotencyProof $proof): void
  {
    $this->assertFacts($this->userFacts($context['user']) + $this->challengeFacts($context['challenge'], 'input_') + $this->policyFacts($message), $proof);
    $success = '1' === ($proof->metadata['success'] ?? null);
    if ($success) {
      $this->assertSuccessfulReplay($message, $context['user']);
    }
    $this->assertOutputChallenge($context, $proof);
    if ($message instanceof ConfirmRegistrationCommand && $success) {
      $this->assertRegistrationReplay($context['user'], $context['challenge'], $proof);
    }
  }

  /**
   * Method assertSuccessfulReplay
   *
   * Requires an available account and current original password proof for successful credential receipts.
   *
   * @access private
   *
   * @param CommandMessage $message supported lifecycle mutation
   * @param ?CredentialLifecycleProof $user current account facts
   *
   * @return void
   */
  private function assertSuccessfulReplay(CommandMessage $message, ?CredentialLifecycleProof $user): void
  {
    if (null !== $user && !in_array($user->status, ['active', 'pending_verification'], true)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if ($message instanceof RegisterUserCommand && (null === $user || !$this->users->verifyPassword($user->userId, $message->password))) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if ($message instanceof RequestPasswordChangeCommand && (null === $user || !$this->users->verifyPassword($user->userId, $message->currentPassword))) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
  }

  /**
   * Method assertOutputChallenge
   *
   * Rechecks the issued pending challenge and its absolute expiry before replay.
   *
   * @access private
   *
   * @param array{principal:string,user:?CredentialLifecycleProof,challenge:?ChallengeLifecycleProof,purpose:OtpPurpose,authenticated:bool} $context current lifecycle owner
   * @param IdempotencyProof $proof authenticated receipt envelope
   *
   * @return void
   */
  private function assertOutputChallenge(array $context, IdempotencyProof $proof): void
  {
    $outputId = $proof->metadata['output_challenge_id'] ?? '';
    if ('' !== $outputId) {
      $output = $this->challenges->byId($outputId);
      $this->assertFacts($this->challengeFacts($output, 'output_'), $proof);
      $user = $context['user'];
      if (null === $output || 'pending' !== $output->status || $output->expiresAt->getTimestamp() <= time() || null === $user || $output->userId !== $user->userId || $output->purpose !== $context['purpose']) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
    }
  }

  /**
   * Method assertRegistrationReplay
   *
   * Binds the verified registration result to its live session and current authorization claims.
   *
   * @access private
   *
   * @param ?CredentialLifecycleProof $user current account facts
   * @param ?ChallengeLifecycleProof $challenge original consumed challenge
   * @param IdempotencyProof $proof authenticated receipt envelope
   *
   * @return void
   */
  private function assertRegistrationReplay(?CredentialLifecycleProof $user, ?ChallengeLifecycleProof $challenge, IdempotencyProof $proof): void
  {
    if (null === $user || 'active' !== $user->status || !$user->emailVerified || null === $challenge || null === $challenge->verifiedAt || ($proof->metadata['session_id'] ?? null) !== $this->sessions->currentSessionId($user->userId, $proof->metadata['access_token_id'] ?? '', $proof->metadata['refresh_token_id'] ?? '') || (int) ($proof->metadata['refresh_expires_at'] ?? 0) <= time() || ($proof->metadata['authorization_fingerprint'] ?? null) !== $this->grants->currentFingerprint($user->userId)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
  }

  /**
   * Method outputChallengeProof
   *
   * Records only the issued challenge facts belonging to the current owner and mailbox.
   *
   * @access private
   *
   * @param array{principal:string,user:?CredentialLifecycleProof,challenge:?ChallengeLifecycleProof,purpose:OtpPurpose,authenticated:bool} $context current lifecycle owner
   * @param array<string, mixed> $values result fields
   *
   * @return array<string, string> issued challenge facts
   */
  private function outputChallengeProof(array $context, array $values): array
  {
    $outputToken = $values['challengeToken'] ?? null;
    if (($values['challenge'] ?? null) instanceof ChallengeInfo) {
      $outputToken = $values['challenge']->challengeToken;
    }
    $output = is_string($outputToken) ? $this->challenges->current($outputToken) : null;
    if (null === $output) {
      return [];
    }
    if (null === $context['user'] || $output->userId !== $context['user']->userId || $output->purpose !== $context['purpose'] || strtolower($output->recipient) !== strtolower($context['user']->email)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $this->challengeFacts($output, 'output_');
  }

  /**
   * Method registrationResultProof
   *
   * Records the issued registration pair and its absolute expiry after resolving its live session.
   *
   * @access private
   *
   * @param CredentialLifecycleProof $user current account facts
   * @param ConfirmRegistrationResult $result successful registration result
   *
   * @return array<string, string> issued session facts
   */
  private function registrationResultProof(CredentialLifecycleProof $user, ConfirmRegistrationResult $result): array
  {
    $pair = null === $result->refreshToken ? null : $this->tokens->decodeRefreshToken($result->refreshToken);
    $sessionId = null === $pair ? null : $this->sessions->currentSessionId($user->userId, $pair['access_token_id'], $pair['refresh_token_id']);
    if (null === $pair || null === $sessionId) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return ['authorization_fingerprint' => $this->grants->issuedFingerprint($user->userId, $result->accessToken ?? throw new IdempotencyDeniedException('result_unavailable')), 'session_id' => $sessionId, 'access_token_id' => $pair['access_token_id'], 'refresh_token_id' => $pair['refresh_token_id'], 'access_expires_at' => (string) (time() + $result->expiresIn), 'refresh_expires_at' => (string) $pair['expires_at']];
  }

  /**
   * Method mfaResendIdentity
   *
   * Resolves the owner and challenge only from a verified preauthentication token.
   *
   * @access private
   *
   * @param MfaResendCommand $message current preauthentication proof
   *
   * @return array{string, string} owner identifier and challenge token
   */
  private function mfaResendIdentity(MfaResendCommand $message): array
  {
    $claims = $this->tokens->decodePreAuthToken($message->preAuthToken);
    $userId = $claims['sub'] ?? null;
    $token = $claims['challenge_token'] ?? null;
    if (!is_string($userId) || !is_string($token) || '' === $userId || '' === $token) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return [$userId, $token];
  }

  /**
   * @param array<string, string> $facts fresh owner facts
   * @param IdempotencyProof $proof authenticated receipt envelope
   */
  private function assertFacts(array $facts, IdempotencyProof $proof): void
  {
    foreach ($facts as $key => $value) {
      if (($proof->metadata[$key] ?? null) !== $value) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
    }
  }
  // #endregion
}
