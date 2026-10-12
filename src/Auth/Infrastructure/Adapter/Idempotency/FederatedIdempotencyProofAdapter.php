<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Contract\Federation\FederatedLogin;
use Auth\Application\Contract\Token\AuthenticationSession;
use Auth\Application\Port\Outbound\Federation\{FederatedFlowRepositoryPort, FederatedProviderClientPort};
use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionAuthorizationProofPort};
use Auth\Application\UseCase\Command\Federation\CompleteFederation\{CompleteFederationCommand, CompleteFederationResult};
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Session\Application\Port\Inbound\SessionReplayProofPort;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\SensitiveIdempotencyProofPort;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function max;
use function time;

/**
 * Class FederatedIdempotencyProofAdapter
 *
 * Authorizes native callbacks through verified provider state and their initiating browser. Completed flows also require current owner revision and live session proof.
 *
 * @category Adapter
 */
final readonly class FederatedIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param FederatedFlowRepositoryPort $flows durable browser-bound verified flow
   * @param FederatedProviderClientPort $providers current enabled provider configuration
   * @param UserSecurityRevisionPort $users fresh owner availability and revision
   * @param JwtTokenServicePort $tokens verified token expiry
   * @param SessionReplayProofPort $sessions current issued pair
   *
   * @return void
   */
  public function __construct(private FederatedFlowRepositoryPort $flows, private FederatedProviderClientPort $providers, private UserSecurityRevisionPort $users, private JwtTokenServicePort $tokens, private SessionReplayProofPort $sessions, private SessionAuthorizationProofPort $grants)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function supports(string $commandClass): bool
  {
    return CompleteFederationCommand::class === $commandClass;
  }

  /**
   * {@inheritDoc}
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    if (!$message instanceof CompleteFederationCommand) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if (!$this->providers->isEnabled($message->provider)) {
      throw new FederatedAuthException('provider_unavailable', 'This sign-in provider is unavailable.');
    }
    $intent = null === $message->userId ? 'login' : 'link';
    $flow = $this->flows->inspect($message->state, $message->browserBinding, $message->provider, $intent);
    if (null === $flow || (null !== $message->userId && $flow->userId !== $message->userId)) {
      throw new FederatedAuthException('invalid_flow', 'This connection request is no longer valid.');
    }
    if ('reserved' === $flow->stage && null !== $flow->exchangeStartedAt) {
      throw new FederatedAuthException('idempotency_outcome_unknown', 'The provider exchange outcome is unknown. Start a new sign-in request.');
    }
    $facts = ['state_hash' => $flow->stateHash, 'browser_binding_hash' => $flow->browserBindingHash, 'provider' => $message->provider->value, 'intent' => $intent];
    if (null !== $storedProof) {
      $proof = $storedProof->metadata;
      if ('completed' !== $flow->stage || ($proof['state_hash'] ?? null) !== $flow->stateHash || ($proof['browser_binding_hash'] ?? null) !== $flow->browserBindingHash || ($proof['provider'] ?? null) !== $message->provider->value || ($proof['intent'] ?? null) !== $intent || null === $flow->completedUserId || ($proof['user_id'] ?? null) !== $flow->completedUserId) {
        throw new FederatedAuthException('invalid_flow', 'This connection request is no longer valid.');
      }
      $current = $this->users->current($flow->completedUserId);
      if (null === $current || !$current->active || ($proof['security_revision'] ?? null) !== (string) $current->securityRevision) {
        throw new FederatedAuthException('account_unavailable', 'This account cannot sign in.');
      }
      if (($proof['authorization_fingerprint'] ?? null) !== $this->grants->currentFingerprint($current->userId)) {
        throw new FederatedAuthException('account_unavailable', 'This account cannot sign in.');
      }
      if (isset($proof['session_id']) && $this->sessions->currentSessionId($current->userId, $proof['access_token_id'] ?? '', $proof['refresh_token_id'] ?? '') !== $proof['session_id']) {
        throw new FederatedAuthException('account_unavailable', 'This account cannot sign in.');
      }
    }

    return new SensitiveIdempotencyAuthorization('federation:' . $flow->stateHash, [
      'provider' => $message->provider->value,
      'intent' => $intent,
      'state' => $message->state,
      'browser_binding' => $message->browserBinding,
      'code' => $message->code,
      'provider_error' => $message->providerError,
      'link_user_id' => $message->userId,
      'trusted_device' => $message->loginContext->trustedDeviceToken,
    ], $facts + ['naturalKeyAllowed' => 'true', 'naturalKeyIdentity' => $flow->stateHash . ':' . $flow->browserBindingHash . ':' . $message->provider->value . ':' . $intent]);
  }

  /**
   * {@inheritDoc}
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    if (!$message instanceof CompleteFederationCommand || !$result instanceof CompleteFederationResult) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $authorization = $this->authorize($message, null);
    $intent = null === $message->userId ? 'login' : 'link';
    $flow = $this->flows->inspect($message->state, $message->browserBinding, $message->provider, $intent);
    $userId = $result->login?->login->userId ?? $message->userId;
    if (null === $flow || 'completed' !== $flow->stage || null === $userId || $flow->completedUserId !== $userId) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $current = $this->users->current($userId);
    if (null === $current || !$current->active) {
      throw new FederatedAuthException('account_unavailable', 'This account cannot sign in.');
    }
    $proof = $authorization->receiptProof + ['user_id' => $userId, 'security_revision' => (string) $current->securityRevision, 'security_revision_after' => (string) $current->securityRevision];
    $login = $result->login?->login;
    $proof['authorization_fingerprint'] = null === $login?->accessToken ? $this->grants->currentFingerprint($userId) : $this->grants->issuedFingerprint($userId, $login->accessToken);
    if (null !== $login) {
      $proof['access_expires_at'] = (string) (time() + $login->expiresIn);
      if (null !== $login->refreshToken) {
        $payload = $this->tokens->decodeRefreshToken($login->refreshToken);
        $sessionId = null === $payload ? null : $this->sessions->currentSessionId($userId, $payload['access_token_id'], $payload['refresh_token_id']);
        if (null === $payload || null === $sessionId) {
          throw new IdempotencyDeniedException('result_unavailable');
        }
        $proof['session_id'] = $sessionId;
        $proof['access_token_id'] = $payload['access_token_id'];
        $proof['refresh_token_id'] = $payload['refresh_token_id'];
        $proof['refresh_expires_at'] = (string) $payload['expires_at'];
      }
    }

    return $proof;
  }

  /**
   * {@inheritDoc}
   */
  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    if (!$result instanceof CompleteFederationResult) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if (null === $result->login) {
      return $result;
    }
    $login = $result->login;
    $session = $login->login;
    $replayed = new AuthenticationSession($session->authenticated, $session->userId, $session->email, $session->accessToken, $session->refreshToken, $session->tokenType, max(0, (int) ($proof->metadata['access_expires_at'] ?? 0) - time()), $session->scopes, $session->mfaRequired, $session->mfaToken, $session->challengeToken, $session->mfaMethod, $session->mfaDestination, $session->errorMessage, $session->errorCode, $session->rateLimitRetryAfter);

    return new CompleteFederationResult(login: new FederatedLogin($replayed, $login->returnUrl, $login->newAccount, $login->browserProofExpiresAt), connections: $result->connections);
  }
  // #endregion
}
