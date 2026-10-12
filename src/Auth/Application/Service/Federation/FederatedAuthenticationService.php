<?php

declare(strict_types=1);

namespace Auth\Application\Service\Federation;

use Auth\Application\Contract\Federation\{
  FederatedConnection,
  FederatedConnections,
  FederatedFlow,
  FederatedLogin,
  FederatedLoginContext,
  FederatedProfile
};
use Auth\Application\Contract\Federation\{FederatedProviderDescriptor, ProviderId};
use Auth\Application\Contract\Token\SignInGrantType;
use Auth\Application\Port\Inbound\Federation\FederationFlowPort;
use Auth\Application\Port\Inbound\InteractiveSessionIssuerPort;
use Auth\Application\Port\Outbound\AuthenticationPolicyPort;
use Auth\Application\Port\Outbound\Federation\{
  FederatedFlowRepositoryPort,
  FederatedIdentityRepositoryPort,
  FederatedProviderClientPort
};
use Auth\Application\UseCase\Command\Federation\CompleteFederation\CompleteFederationResult;
use Auth\Application\UseCase\Command\Federation\DisconnectFederation\DisconnectFederationResult;
use Auth\Domain\Exception\Federation\{FederatedAuthException, FederatedConflictException, FederatedUnauthorizedException};
use DateInterval;
use DateTimeImmutable;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyExecutionPort;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use User\Application\Port\Inbound\{FederatedUserPort, UserSecurityRevisionPort};

use function array_filter;
use function array_values;
use function base64_encode;
use function bin2hex;
use function hash;
use function parse_url;
use function preg_match;
use function random_bytes;
use function rtrim;
use function str_starts_with;
use function strtr;

use const PHP_URL_HOST;
use const PHP_URL_SCHEME;

/**
 * Service FederatedAuthenticationService.
 *
 * Owns the stateful Google/Microsoft authorization-code flow, account
 * provisioning, explicit linking and unlinking invariants.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedAuthenticationService implements FederationFlowPort
{
  /**
   * Constant LOGIN
   *
   * Intent label stored for federated sign-in flows.
   *
   * @access private
   *
   * @var string
   */
  private const string LOGIN = 'login';

  /**
   * Constant LINK
   *
   * Intent label stored for federated account-linking flows.
   *
   * @access private
   *
   * @var string
   */
  private const string LINK = 'link';

  /**
   * Constant INVALID_FLOW_MESSAGE
   *
   * User-facing message returned for invalid or expired flow state.
   *
   * @access private
   *
   * @var string
   */
  private const string INVALID_FLOW_MESSAGE = 'This connection request is no longer valid.';

  /**
   * Method __construct
   *
   * Initializes federated provider, flow, identity, session and user services.
   *
   * @access public
   *
   * @param FederatedProviderClientPort $providerClient provider authorization client
   * @param FederatedFlowRepositoryPort $flows one-time flow persistence
   * @param FederatedIdentityRepositoryPort $identities linked identity persistence
   * @param TransactionManagerPort $transaction transaction boundary
   * @param FederatedUserPort $users user account operations
   * @param InteractiveSessionIssuerPort $sessionIssuer session creation service
   * @param string $frontendUrl configured frontend base URL
   *
   * @return void
   */
  public function __construct(
    private FederatedProviderClientPort $providerClient,
    private FederatedFlowRepositoryPort $flows,
    private FederatedIdentityRepositoryPort $identities,
    private TransactionManagerPort $transaction,
    private FederatedUserPort $users,
    private InteractiveSessionIssuerPort $sessionIssuer,
    private string $frontendUrl,
    private AuthenticationPolicyPort $authenticationPolicy,
    private ?HttpIdempotencyExecutionPort $execution = null,
    private ?UserSecurityRevisionPort $securityRevisions = null,
  ) {
  }

  /**
   * Returns only providers that are enabled and have complete credentials.
   *
   * @since 1.0.0
   *
   * @return list<FederatedProviderDescriptor>
   */
  public function enabledProviders(): array
  {
    return array_values(array_filter(
      $this->providerClient->descriptors(),
      static fn (FederatedProviderDescriptor $descriptor): bool => $descriptor->enabled,
    ));
  }

  /**
   * Starts a public sign-in flow and persists its short-lived state.
   *
   * @since 1.0.0
   */
  public function startLogin(ProviderId $provider, string $returnUrl, ?string $browserBinding = null): string
  {
    return $this->start($provider, self::LOGIN, null, $returnUrl, $browserBinding ?? $this->randomToken());
  }

  /**
   * Starts an authenticated provider-linking flow for one user.
   *
   * @since 1.0.0
   */
  public function startLink(ProviderId $provider, string $userId, ?string $browserBinding = null): string
  {
    return $this->start($provider, self::LINK, $userId, '/account/security', $browserBinding ?? $this->randomToken());
  }

  /**
   * Consumes a sign-in callback and issues the standard Fireguard session result.
   *
   * @since 1.0.0
   */
  public function completeLogin(
    ProviderId $provider,
    string $state,
    string $code,
    string $browserBinding,
    ?string $providerError,
    FederatedLoginContext $loginContext,
  ): FederatedLogin {
    $flow = $this->consume($provider, self::LOGIN, $state, $browserBinding);
    $profile = $this->providerProfile($provider, $flow, $code, $providerError);

    return $this->transaction->transactional(function () use (
      $provider,
      $profile,
      $flow,
      $loginContext,
      $state,
      $browserBinding,
    ): FederatedLogin {
      $connection = $this->identities->findBySubject($provider, $profile->subject);
      $newAccount = false;

      if (null !== $connection) {
        if ($connection->issuer !== $profile->issuer) {
          throw new FederatedAuthException('issuer_mismatch', 'This sign-in provider configuration has changed.');
        }
        $user = $this->users->recordSuccessfulLogin($connection->userId);
        if (null === $user) {
          throw new FederatedUnauthorizedException('account_unavailable', 'This account cannot sign in.');
        }
        $this->touchConnection($connection, $profile);
      } else {
        if (!$this->authenticationPolicy->federatedProvisioningEnabled()) {
          throw new FederatedAuthException('federated_provisioning_disabled', 'New accounts cannot be created through this provider.');
        }
        $user = $this->users->provision($profile->email, $profile->firstName, $profile->lastName);
        if (null === $user) {
          throw new FederatedConflictException(
            'account_exists',
            'Sign in with your email and password, then connect this provider from Security.',
          );
        }
        $newAccount = true;
        $this->identities->save($this->newConnection($user->userId, $profile));
        $user = $this->users->recordSuccessfulLogin($user->userId) ?? $user;
      }

      $login = new FederatedLogin(
        login: $this->sessionIssuer->issue(
          userId: $user->userId,
          email: $user->email,
          ipAddress: $loginContext->ipAddress,
          userAgent: $loginContext->userAgent,
          trustedDeviceToken: $loginContext->trustedDeviceToken,
          rememberMe: false,
          grantType: match ($provider->value) {
            'google' => SignInGrantType::GOOGLE,
            'microsoft' => SignInGrantType::MICROSOFT,
            default => SignInGrantType::OIDC,
          },
        ),
        returnUrl: $flow->returnUrl,
        newAccount: $newAccount,
      );
      $this->flows->markCompleted($flow->stateHash, $user->userId);
      $completed = $this->flows->inspect($state, $browserBinding, $provider, self::LOGIN) ?? throw new FederatedAuthException('invalid_flow', self::INVALID_FLOW_MESSAGE);
      $deadline = $completed->proofExpiresAt ?? throw new FederatedAuthException('invalid_flow', self::INVALID_FLOW_MESSAGE);
      $login = new FederatedLogin($login->login, $login->returnUrl, $login->newAccount, $deadline);
      $this->execution?->captureResult(new CompleteFederationResult(login: $login));

      return $login;
    });
  }

  /**
   * Consumes a link callback without changing the signed-in user's profile.
   *
   * @since 1.0.0
   */
  public function completeLink(
    ProviderId $provider,
    string $state,
    string $code,
    string $userId,
    string $browserBinding,
    ?string $providerError,
  ): FederatedConnections {
    $flow = $this->consume($provider, self::LINK, $state, $browserBinding);
    if ($flow->userId !== $userId) {
      throw new FederatedAuthException('invalid_flow', self::INVALID_FLOW_MESSAGE);
    }

    $profile = $this->providerProfile($provider, $flow, $code, $providerError);

    return $this->transaction->transactional(function () use ($provider, $profile, $userId, $flow, $state, $browserBinding): FederatedConnections {
      $ownedBySubject = $this->identities->findBySubject($provider, $profile->subject);
      if (null !== $ownedBySubject && $ownedBySubject->userId !== $userId) {
        throw new FederatedConflictException('identity_linked', 'This provider account is already connected.');
      }

      $ownedByUser = $this->identities->findForUserProvider($userId, $provider);
      if (null !== $ownedByUser && $ownedByUser->subject !== $profile->subject) {
        throw new FederatedConflictException('provider_already_linked', 'A different provider account is already connected.');
      }

      if (null !== $ownedByUser) {
        $this->touchConnection($ownedByUser, $profile);
      } else {
        $this->identities->save($this->newConnection($userId, $profile));
        $this->securityRevisions?->advance($userId);
      }

      $this->flows->markCompleted($flow->stateHash, $userId);
      $completed = $this->flows->inspect($state, $browserBinding, $provider, self::LINK) ?? throw new FederatedAuthException('invalid_flow', self::INVALID_FLOW_MESSAGE);
      $deadline = $completed->proofExpiresAt ?? throw new FederatedAuthException('invalid_flow', self::INVALID_FLOW_MESSAGE);
      $current = $this->connections($userId);
      $connections = new FederatedConnections($current->passwordConfigured, $current->connections, $current->lastSignInMethod, $deadline);
      $this->execution?->captureResult(new CompleteFederationResult(connections: $connections));

      return $connections;
    });
  }

  /**
   * Lists every sign-in method configured for a user.
   *
   * @since 1.0.0
   */
  public function connections(string $userId): FederatedConnections
  {
    $user = $this->users->find($userId);
    if (null === $user) {
      throw new FederatedUnauthorizedException('account_unavailable', 'This account is unavailable.');
    }

    $connections = $this->identities->findForUser($userId);

    return new FederatedConnections(
      passwordConfigured: $user->passwordConfigured,
      connections: $connections,
      lastSignInMethod: $user->lastSignInMethod,
    );
  }

  /**
   * Removes one provider while preserving at least one usable sign-in method.
   *
   * @since 1.0.0
   */
  public function remove(ProviderId $provider, string $userId): FederatedConnections
  {
    return $this->transaction->transactional(function () use ($provider, $userId): FederatedConnections {
      $user = $this->users->find($userId);
      if (null === $user) {
        throw new FederatedUnauthorizedException('account_unavailable', 'This account is unavailable.');
      }

      if (!$this->identities->removePreservingAccess($provider, $userId, $user->passwordConfigured)) {
        throw new FederatedConflictException('last_sign_in_method', 'Add another sign-in method before removing this one.');
      }
      $this->securityRevisions?->advance($userId);

      $connections = $this->connections($userId);
      $this->execution?->captureResult(new DisconnectFederationResult($connections));

      return $connections;
    });
  }

  /**
   * Method start
   *
   * Creates a short-lived authorization flow and returns its provider URL.
   *
   * @access private
   *
   * @param ProviderId $provider selected identity provider
   * @param string $intent flow purpose
   * @param ?string $userId linking user, when applicable
   * @param string $returnUrl post-flow relative destination
   * @param string $browserBinding browser binding token
   *
   * @return string provider authorization URL
   *
   * @throws FederatedAuthException when the provider is unavailable
   */
  private function start(
    ProviderId $provider,
    string $intent,
    ?string $userId,
    string $returnUrl,
    string $browserBinding,
  ): string {
    if (!$this->providerClient->isEnabled($provider)) {
      throw new FederatedAuthException('provider_unavailable', 'This sign-in provider is unavailable.');
    }

    $state = $this->randomToken();
    $redirectUri = $this->callbackUri($provider, $intent);
    $authorization = $this->providerClient->start($provider, $redirectUri, $state);
    $this->flows->save(new FederatedFlow(
      stateHash: hash('sha256', $state),
      browserBindingHash: hash('sha256', $browserBinding),
      provider: $provider,
      intent: $intent,
      userId: $userId,
      codeVerifier: $authorization->codeVerifier,
      redirectUri: $redirectUri,
      returnUrl: $this->safeReturnUrl($returnUrl),
      expiresAt: new DateTimeImmutable()->add(new DateInterval('PT10M')),
      nonceHash: $authorization->nonceHash,
    ));

    return $authorization->authorizationUrl;
  }

  /**
   * Method consume
   *
   * Consumes and validates a one-time provider flow for the expected intent.
   *
   * @access private
   *
   * @param ProviderId $provider expected provider
   * @param string $intent expected flow purpose
   * @param string $state authorization state
   * @param string $browserBinding browser binding token
   *
   * @return FederatedFlow validated flow
   *
   * @throws FederatedAuthException when the state is invalid or unavailable
   */
  private function consume(ProviderId $provider, string $intent, string $state, string $browserBinding): FederatedFlow
  {
    if (!$this->providerClient->isEnabled($provider)) {
      throw new FederatedAuthException('provider_unavailable', 'This sign-in provider is unavailable.');
    }
    if ('' === $state) {
      throw new FederatedAuthException('invalid_flow', self::INVALID_FLOW_MESSAGE);
    }
    $flow = $this->flows->consume($state, $browserBinding, $provider, $intent);
    if (null === $flow) {
      throw new FederatedAuthException('invalid_flow', self::INVALID_FLOW_MESSAGE);
    }

    return $flow;
  }

  /**
   * Method providerProfile
   *
   * Completes provider authentication and requires verified email in its profile.
   *
   * @access private
   *
   * @param ProviderId $provider selected provider
   * @param FederatedFlow $flow validated flow
   * @param string $code provider authorization code
   * @param ?string $providerError callback error, if supplied
   *
   * @return FederatedProfile authenticated provider profile
   *
   * @throws FederatedAuthException when the provider denies sign-in or email is unverified
   */
  private function providerProfile(
    ProviderId $provider,
    FederatedFlow $flow,
    string $code,
    ?string $providerError,
  ): FederatedProfile {
    if (null !== $providerError && '' !== $providerError) {
      throw new FederatedAuthException('provider_cancelled', 'Sign-in was cancelled.');
    }
    if ('' === $code) {
      throw new FederatedAuthException('provider_cancelled', 'Sign-in was cancelled.');
    }
    $profile = $flow->verifiedProfile ?? $this->providerClient->complete($provider, $flow->redirectUri, $code, $flow->codeVerifier, $flow->nonceHash);
    if ($profile->provider->value !== $provider->value) {
      throw new FederatedAuthException('identity_mismatch', 'The identity provider returned an unexpected identity.');
    }
    if (null !== $flow->nonceHash && null === $profile->issuer) {
      throw new FederatedAuthException('issuer_mismatch', 'The identity provider returned an unexpected issuer.');
    }
    if (null !== $profile->issuer && $this->identities->hasIssuerConflict($provider, $profile->issuer)) {
      throw new FederatedAuthException('issuer_mismatch', 'This sign-in provider configuration has changed.');
    }
    if (!$profile->emailVerified) {
      throw new FederatedAuthException('email_unverified', 'The provider email is not verified.');
    }
    if (null === $flow->verifiedProfile) {
      $this->flows->recordVerified($flow->stateHash, $profile);
    }

    return $profile;
  }

  /**
   * Method newConnection
   *
   * Creates a provider identity connection with initial timestamps.
   *
   * @access private
   *
   * @param string $userId owning user identifier
   * @param FederatedProfile $profile verified provider profile
   *
   * @return FederatedConnection new connection
   */
  private function newConnection(string $userId, FederatedProfile $profile): FederatedConnection
  {
    $now = new DateTimeImmutable();

    return new FederatedConnection(
      id: bin2hex(random_bytes(16)),
      userId: $userId,
      provider: $profile->provider,
      subject: $profile->subject,
      email: $profile->email,
      connectedAt: $now,
      lastUsedAt: $now,
      issuer: $profile->issuer,
    );
  }

  /**
   * Method touchConnection
   *
   * Persists a connection with refreshed email and last-use timestamp.
   *
   * @access private
   *
   * @param FederatedConnection $connection existing connection
   * @param FederatedProfile $profile latest verified provider profile
   *
   * @return void
   */
  private function touchConnection(FederatedConnection $connection, FederatedProfile $profile): void
  {
    if ($connection->issuer !== $profile->issuer) {
      throw new FederatedAuthException('issuer_mismatch', 'This sign-in provider configuration has changed.');
    }
    $this->identities->save(new FederatedConnection(
      id: $connection->id,
      userId: $connection->userId,
      provider: $connection->provider,
      subject: $connection->subject,
      email: $profile->email,
      connectedAt: $connection->connectedAt,
      lastUsedAt: new DateTimeImmutable(),
      issuer: $connection->issuer,
    ));
  }

  /**
   * Method callbackUri
   *
   * Builds the frontend callback path for sign-in or account linking.
   *
   * @access private
   *
   * @param ProviderId $provider selected provider
   * @param string $intent flow purpose
   *
   * @return string callback URI
   */
  private function callbackUri(ProviderId $provider, string $intent): string
  {
    $base = rtrim($this->frontendUrl, '/');

    return self::LINK === $intent
      ? $base . '/account/security/federated/' . $provider->value . '/callback'
      : $base . '/auth/federated/' . $provider->value . '/callback';
  }

  /**
   * Method safeReturnUrl
   *
   * Restricts post-flow navigation to relative paths without host or scheme.
   *
   * @access private
   *
   * @param string $returnUrl requested destination
   *
   * @return string safe relative path or the root path
   */
  private function safeReturnUrl(string $returnUrl): string
  {
    if (
      '' === $returnUrl
      || !str_starts_with($returnUrl, '/')
      || str_starts_with($returnUrl, '//')
      || 1 === preg_match('/[\\\\\x00-\x1F\x7F]/', $returnUrl)
      || null !== parse_url($returnUrl, PHP_URL_SCHEME)
      || null !== parse_url($returnUrl, PHP_URL_HOST)
    ) {
      return '/';
    }

    return $returnUrl;
  }

  /**
   * Method randomToken
   *
   * Generates a URL-safe random flow token.
   *
   * @access private
   *
   * @return string random token
   */
  private function randomToken(): string
  {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
  }
}
