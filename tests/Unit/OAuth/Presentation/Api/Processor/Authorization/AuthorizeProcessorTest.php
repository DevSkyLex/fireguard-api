<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Processor\Authorization;

use ApiPlatform\Metadata\Operation;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Nyholm\Psr7\Response as Psr7Response;
use OAuth\Application\Port\Outbound\Token\AuthCodeRepositoryPort;
use OAuth\Application\Port\Outbound\User\OidcUserProviderPort;
use OAuth\Application\UseCase\Query\Consent\CheckConsent\CheckConsentQuery;
use OAuth\Application\UseCase\Query\Consent\CheckConsent\CheckConsentResult;
use OAuth\Domain\Model\Oidc\OidcUser;
use OAuth\Infrastructure\OAuth2\League\Entity\Client;
use OAuth\Infrastructure\OAuth2\League\Entity\Scope;
use OAuth\Presentation\Api\Processor\Authorization\AuthorizeProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;

/**
 * Test AuthorizeProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: AuthorizeProcessor::class)]
final class AuthorizeProcessorTest extends TestCase
{
  // #region Methods
  /**
   * Method testProcessReturnsInvalidRequestWhenPromptNoneCombined.
   *
   * Test that prompt=none combined with other values returns invalid_request.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessReturnsInvalidRequestWhenPromptNoneCombined(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
        'code_challenge_method' => 'S256',
        'prompt' => 'none login',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authorizationServer = $this->createAuthorizationServerMock(
      authRequest: $this->createAuthorizationRequest(),
    );
    $authorizationServer->expects(self::never())
      ->method('completeAuthorizationRequest');

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock(null),
      queryBus: $queryBus,
      requestStack: $requestStack,
      authCodeRepository: $this->createMock(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createMock(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: JsonResponse::class, actual: $response);
    self::assertSame(expected: Response::HTTP_BAD_REQUEST, actual: $response->getStatusCode());

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertSame(expected: 'invalid_request', actual: $body['error'] ?? null);
  }

  /**
   * Method testProcessReturnsLoginRequiredWhenMaxAgeExceeded.
   *
   * Test that max_age exceeding auth time returns login_required.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessReturnsLoginRequiredWhenMaxAgeExceeded(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
        'code_challenge_method' => 'S256',
        'max_age' => '1',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authorizationServer = $this->createAuthorizationServerMock(
      authRequest: $this->createAuthorizationRequest(),
    );
    $authorizationServer->expects(self::never())
      ->method('completeAuthorizationRequest');

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $authTime = new DateTimeImmutable('-10 seconds');
    $oidcUserProvider = $this->createMock(OidcUserProviderPort::class);
    $oidcUserProvider->expects(self::once())
      ->method('findByIdentifier')
      ->with('user-123')
      ->willReturn(new OidcUser(
        subject: 'user-123',
        preferredUsername: 'user',
        email: 'user@example.com',
        emailVerified: true,
        givenName: null,
        familyName: null,
        pictureUrl: null,
        authTime: $authTime,
      ));

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($this->createSecurityUser()),
      queryBus: $queryBus,
      requestStack: $requestStack,
      authCodeRepository: $this->createMock(AuthCodeRepositoryPort::class),
      oidcUserProvider: $oidcUserProvider,
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: JsonResponse::class, actual: $response);
    self::assertSame(expected: Response::HTTP_UNAUTHORIZED, actual: $response->getStatusCode());

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertSame(expected: 'login_required', actual: $body['error'] ?? null);
  }

  /**
   * Method testProcessReturnsInvalidRequestWhenMaxAgeNotNumeric.
   *
   * Test that non-numeric max_age returns invalid_request.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessReturnsInvalidRequestWhenMaxAgeNotNumeric(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
        'code_challenge_method' => 'S256',
        'max_age' => 'abc',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authorizationServer = $this->createAuthorizationServerMock(
      authRequest: $this->createAuthorizationRequest(),
    );
    $authorizationServer->expects(self::never())
      ->method('completeAuthorizationRequest');

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock(null),
      queryBus: $queryBus,
      requestStack: $requestStack,
      authCodeRepository: $this->createMock(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createMock(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: JsonResponse::class, actual: $response);
    self::assertSame(expected: Response::HTTP_BAD_REQUEST, actual: $response->getStatusCode());

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertSame(expected: 'invalid_request', actual: $body['error'] ?? null);
  }

  /**
   * Method testProcessReturnsConsentRequiredWhenPromptConsent.
   *
   * Test that prompt=consent forces consent even if already granted.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessReturnsConsentRequiredWhenPromptConsent(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
        'code_challenge_method' => 'S256',
        'prompt' => 'consent',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authorizationServer = $this->createAuthorizationServerMock(
      authRequest: $this->createAuthorizationRequest(),
    );
    $authorizationServer->expects(self::never())
      ->method('completeAuthorizationRequest');

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(CheckConsentQuery::class))
      ->willReturn(new CheckConsentResult(
        hasConsent: true,
        grantedScopes: ['openid'],
        missingScopes: [],
        requiresConsentScreen: false,
      ));

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($this->createSecurityUser()),
      queryBus: $queryBus,
      requestStack: $requestStack,
      authCodeRepository: $this->createMock(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createMock(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: JsonResponse::class, actual: $response);
    self::assertSame(expected: Response::HTTP_FORBIDDEN, actual: $response->getStatusCode());

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertSame(expected: 'consent_required', actual: $body['error'] ?? null);
  }

  /**
   * Method testProcessReturnsInvalidRequestWhenPromptUnknown.
   *
   * Test that invalid prompt value returns invalid_request.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessReturnsInvalidRequestWhenPromptUnknown(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
        'code_challenge_method' => 'S256',
        'prompt' => 'banana',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authorizationServer = $this->createAuthorizationServerMock(
      authRequest: $this->createAuthorizationRequest(),
    );
    $authorizationServer->expects(self::never())
      ->method('completeAuthorizationRequest');

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock(null),
      queryBus: $queryBus,
      requestStack: $requestStack,
      authCodeRepository: $this->createMock(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createMock(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: JsonResponse::class, actual: $response);
    self::assertSame(expected: Response::HTTP_BAD_REQUEST, actual: $response->getStatusCode());

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertSame(expected: 'invalid_request', actual: $body['error'] ?? null);
  }

  /**
   * Method testProcessReturnsLoginRequiredWhenPromptLogin.
   *
   * Test that prompt=login returns login_required even when authenticated.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessReturnsLoginRequiredWhenPromptLogin(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
        'code_challenge_method' => 'S256',
        'prompt' => 'login',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authorizationServer = $this->createAuthorizationServerMock(
      authRequest: $this->createAuthorizationRequest(),
    );
    $authorizationServer->expects(self::never())
      ->method('completeAuthorizationRequest');

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($this->createSecurityUser()),
      queryBus: $queryBus,
      requestStack: $requestStack,
      authCodeRepository: $this->createMock(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createMock(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: JsonResponse::class, actual: $response);
    self::assertSame(expected: Response::HTTP_UNAUTHORIZED, actual: $response->getStatusCode());

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertSame(expected: 'login_required', actual: $body['error'] ?? null);
  }

  /**
   * Method testProcessAllowsMissingCodeChallengeMethod.
   *
   * Test that missing code_challenge_method defaults to plain.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessAllowsMissingCodeChallengeMethod(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authRequest = $this->createAuthorizationRequest();

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->willReturn(new Psr7Response(302, ['Location' => 'https://client.example.com/callback?code=auth-code']));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(CheckConsentQuery::class))
      ->willReturn(new CheckConsentResult(
        hasConsent: true,
        grantedScopes: ['openid'],
        missingScopes: [],
        requiresConsentScreen: false,
      ));

    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::never())->method('updateNonce');

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($this->createSecurityUser()),
      queryBus: $queryBus,
      requestStack: $requestStack,
      authCodeRepository: $authCodeRepository,
      oidcUserProvider: $this->createMock(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: Response::class, actual: $response);
    self::assertSame(expected: Response::HTTP_FOUND, actual: $response->getStatusCode());
  }

  /**
   * Method testProcessStoresNonceFromFragmentResponse.
   *
   * Test that nonce is stored when the response uses a fragment.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessStoresNonceFromFragmentResponse(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
        'code_challenge_method' => 'S256',
        'nonce' => 'nonce-value',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authRequest = $this->createAuthorizationRequest();

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->willReturn(new Psr7Response(302, ['Location' => 'https://client.example.com/callback#code=fragment-code']));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(CheckConsentQuery::class))
      ->willReturn(new CheckConsentResult(
        hasConsent: true,
        grantedScopes: ['openid'],
        missingScopes: [],
        requiresConsentScreen: false,
      ));

    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::once())
      ->method('updateNonce')
      ->with('fragment-code', 'nonce-value');

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($this->createSecurityUser()),
      queryBus: $queryBus,
      requestStack: $requestStack,
      authCodeRepository: $authCodeRepository,
      oidcUserProvider: $this->createMock(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: Response::class, actual: $response);
  }

  /**
   * Method testProcessStoresNonceFromFormPostResponse.
   *
   * Test that nonce is stored when the response uses form_post.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessStoresNonceFromFormPostResponse(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
        'code_challenge_method' => 'S256',
        'nonce' => 'nonce-value',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authRequest = $this->createAuthorizationRequest();

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->willReturn(new Psr7Response(
        200,
        ['Content-Type' => 'text/html'],
        '<input type="hidden" name="code" value="form-code" />',
      ));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(CheckConsentQuery::class))
      ->willReturn(new CheckConsentResult(
        hasConsent: true,
        grantedScopes: ['openid'],
        missingScopes: [],
        requiresConsentScreen: false,
      ));

    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::once())
      ->method('updateNonce')
      ->with('form-code', 'nonce-value');

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($this->createSecurityUser()),
      queryBus: $queryBus,
      requestStack: $requestStack,
      authCodeRepository: $authCodeRepository,
      oidcUserProvider: $this->createMock(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: Response::class, actual: $response);
  }

  /**
   * @return AuthorizationServer&\PHPUnit\Framework\MockObject\MockObject
   */
  private function createAuthorizationServerMock(AuthorizationRequest $authRequest): AuthorizationServer
  {
    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authRequest);

    return $authorizationServer;
  }

  private function createAuthorizationRequest(): AuthorizationRequest
  {
    $client = new Client(
      identifier: 'client-123',
      name: 'Test Client',
      redirectUri: 'https://client.example.com/callback',
    );

    $scope = new Scope();
    $scope->setIdentifier('openid');

    $authRequest = new AuthorizationRequest();
    $authRequest->setClient($client);
    $authRequest->setRedirectUri('https://client.example.com/callback');
    $authRequest->setState('state-123');
    $authRequest->setScopes([$scope]);

    return $authRequest;
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hashed',
      roles: ['ROLE_USER'],
      scopes: ['openid'],
      isActive: true,
    );
  }

  private function createSecurityMock(?SecurityUser $user): Security
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($user);

    return $security;
  }
  // #endregion
}
