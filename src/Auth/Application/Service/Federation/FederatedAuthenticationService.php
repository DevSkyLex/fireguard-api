<?php

declare(strict_types=1);

namespace Auth\Application\Service\Federation;

use Auth\Application\Contract\Federation\{
  FederatedConnection,
  FederatedConnections,
  FederatedFlow,
  FederatedLogin,
  FederatedProfile
};
use Auth\Application\Port\Outbound\Federation\{
  FederatedFlowRepositoryPort,
  FederatedIdentityRepositoryPort,
  FederatedProviderClientPort
};
use Auth\Domain\Exception\Federation\{FederatedAuthException, FederatedConflictException, FederatedUnauthorizedException};
use Auth\Domain\ValueObject\Federation\FederatedProvider;
use Auth\Domain\ValueObject\Security\SignInGrantType;
use DateInterval;
use DateTimeImmutable;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use User\Application\Port\Inbound\FederatedUserPort;

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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedAuthenticationService
{
  private const string LOGIN = 'login';

  private const string LINK = 'link';

  public function __construct(
    private FederatedProviderClientPort $providerClient,
    private FederatedFlowRepositoryPort $flows,
    private FederatedIdentityRepositoryPort $identities,
    private TransactionManagerPort $transaction,
    private FederatedUserPort $users,
    private SessionIssuer $sessionIssuer,
    #[Autowire('%env(APP_FRONTEND_URL)%')]
    private string $frontendUrl,
  ) {
  }

  /**
   * Returns only providers that are enabled and have complete credentials.
   *
   * @since 1.0.0
   *
   * @return list<FederatedProvider>
   */
  public function enabledProviders(): array
  {
    return array_values(array_filter(
      FederatedProvider::cases(),
      $this->providerClient->isEnabled(...),
    ));
  }

  /**
   * Starts a public sign-in flow and persists its short-lived state.
   *
   * @since 1.0.0
   */
  public function startLogin(FederatedProvider $provider, string $returnUrl, ?string $browserBinding = null): string
  {
    return $this->start($provider, self::LOGIN, null, $returnUrl, $browserBinding ?? $this->randomToken());
  }

  /**
   * Starts an authenticated provider-linking flow for one user.
   *
   * @since 1.0.0
   */
  public function startLink(FederatedProvider $provider, string $userId, ?string $browserBinding = null): string
  {
    return $this->start($provider, self::LINK, $userId, '/account/security', $browserBinding ?? $this->randomToken());
  }

  /**
   * Consumes a sign-in callback and issues the standard Fireguard session result.
   *
   * @since 1.0.0
   */
  public function completeLogin(
    FederatedProvider $provider,
    string $state,
    string $code,
    string $browserBinding,
    ?string $providerError,
    ?string $ipAddress,
    ?string $userAgent,
    ?string $trustedDeviceToken,
  ): FederatedLogin {
    $flow = $this->consume($provider, self::LOGIN, $state, $browserBinding);
    $profile = $this->providerProfile($provider, $flow, $code, $providerError);

    return $this->transaction->transactional(function () use (
      $provider,
      $profile,
      $flow,
      $ipAddress,
      $userAgent,
      $trustedDeviceToken,
    ): FederatedLogin {
      $connection = $this->identities->findBySubject($provider, $profile->subject);
      $newAccount = false;

      if (null !== $connection) {
        $user = $this->users->recordSuccessfulLogin($connection->userId);
        if (null === $user) {
          throw new FederatedUnauthorizedException('account_unavailable', 'This account cannot sign in.');
        }
        $this->touchConnection($connection, $profile);
      } else {
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

      return new FederatedLogin(
        login: $this->sessionIssuer->issue(
          userId: $user->userId,
          email: $user->email,
          ipAddress: $ipAddress,
          userAgent: $userAgent,
          trustedDeviceToken: $trustedDeviceToken,
          rememberMe: false,
          grantType: match ($provider) {
            FederatedProvider::GOOGLE => SignInGrantType::GOOGLE,
            FederatedProvider::MICROSOFT => SignInGrantType::MICROSOFT,
          },
        ),
        returnUrl: $flow->returnUrl,
        newAccount: $newAccount,
      );
    });
  }

  /**
   * Consumes a link callback without changing the signed-in user's profile.
   *
   * @since 1.0.0
   */
  public function completeLink(
    FederatedProvider $provider,
    string $state,
    string $code,
    string $userId,
    string $browserBinding,
    ?string $providerError,
  ): FederatedConnections {
    $flow = $this->consume($provider, self::LINK, $state, $browserBinding);
    if ($flow->userId !== $userId) {
      throw new FederatedAuthException('invalid_flow', 'This connection request is no longer valid.');
    }

    $profile = $this->providerProfile($provider, $flow, $code, $providerError);
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
    }

    return $this->connections($userId);
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
  public function remove(FederatedProvider $provider, string $userId): FederatedConnections
  {
    $user = $this->users->find($userId);
    if (null === $user) {
      throw new FederatedUnauthorizedException('account_unavailable', 'This account is unavailable.');
    }

    if (!$this->identities->removePreservingAccess($provider, $userId, $user->passwordConfigured)) {
      throw new FederatedConflictException('last_sign_in_method', 'Add another sign-in method before removing this one.');
    }

    return $this->connections($userId);
  }

  private function start(
    FederatedProvider $provider,
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
    ));

    return $authorization->authorizationUrl;
  }

  private function consume(FederatedProvider $provider, string $intent, string $state, string $browserBinding): FederatedFlow
  {
    if ('' === $state) {
      throw new FederatedAuthException('invalid_flow', 'This connection request is no longer valid.');
    }
    $flow = $this->flows->consume($state, $browserBinding, $provider, $intent);
    if (null === $flow) {
      throw new FederatedAuthException('invalid_flow', 'This connection request is no longer valid.');
    }

    return $flow;
  }

  private function providerProfile(
    FederatedProvider $provider,
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
    $profile = $this->providerClient->complete($provider, $flow->redirectUri, $code, $flow->codeVerifier);
    if (!$profile->emailVerified) {
      throw new FederatedAuthException('email_unverified', 'The provider email is not verified.');
    }

    return $profile;
  }

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
    );
  }

  private function touchConnection(FederatedConnection $connection, FederatedProfile $profile): void
  {
    $this->identities->save(new FederatedConnection(
      id: $connection->id,
      userId: $connection->userId,
      provider: $connection->provider,
      subject: $connection->subject,
      email: $profile->email,
      connectedAt: $connection->connectedAt,
      lastUsedAt: new DateTimeImmutable(),
    ));
  }

  private function callbackUri(FederatedProvider $provider, string $intent): string
  {
    $base = rtrim($this->frontendUrl, '/');

    return self::LINK === $intent
      ? $base . '/account/security/federated/' . $provider->value . '/callback'
      : $base . '/auth/federated/' . $provider->value . '/callback';
  }

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

  private function randomToken(): string
  {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
  }
}
