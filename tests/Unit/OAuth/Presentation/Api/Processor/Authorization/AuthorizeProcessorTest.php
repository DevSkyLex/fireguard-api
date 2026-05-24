<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Processor\Authorization;

use ApiPlatform\Metadata\Operation;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Nyholm\Psr7\Response as Psr7Response;
use OAuth\Application\Port\Outbound\Token\AuthCodeRepositoryPort;
use OAuth\Application\Port\Outbound\User\OidcUserProviderPort;
use OAuth\Application\UseCase\Query\Consent\CheckConsent\{CheckConsentQuery, CheckConsentResult};
use OAuth\Domain\Model\Oidc\OidcUser;
use OAuth\Infrastructure\OAuth2\League\Entity\{Client, Scope};
use OAuth\Presentation\Api\Processor\Authorization\AuthorizeProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, RequestStack, Response};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function hash;
use function json_decode;
use function sprintf;
use function strlen;
use function substr;

use const SEEK_CUR;
use const SEEK_END;
use const SEEK_SET;

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
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

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
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $oidcUserProvider,
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

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
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

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
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

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
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

    self::assertInstanceOf(expected: JsonResponse::class, actual: $response);
    self::assertSame(expected: Response::HTTP_BAD_REQUEST, actual: $response->getStatusCode());

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertSame(expected: 'invalid_request', actual: $body['error'] ?? null);
  }

  #[Test]
  public function testProvideThrowsWhenRequestMissing(): void
  {
    $requestStack = new RequestStack();

    $processor = new AuthorizeProcessor(
      authorizationServer: $this->createStub(AuthorizationServer::class),
      security: $this->createSecurityMock(null),
      queryBus: $this->createStub(QueryBusPort::class),
      requestStack: $requestStack,
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->provide(operation: $this->createStub(Operation::class));
  }

  #[Test]
  public function testProcessReturnsInvalidRequestWhenCodeChallengeMissing(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::never())->method('validateAuthorizationRequest');

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock(null),
      queryBus: $this->createStub(QueryBusPort::class),
      requestStack: $requestStack,
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

    self::assertInstanceOf(JsonResponse::class, $response);
    self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
  }

  #[Test]
  public function testProcessReturnsInvalidRequestWhenCodeChallengeMethodInvalid(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
        'code_challenge_method' => 'invalid',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::never())->method('validateAuthorizationRequest');

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock(null),
      queryBus: $this->createStub(QueryBusPort::class),
      requestStack: $requestStack,
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

    self::assertInstanceOf(JsonResponse::class, $response);
    self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
  }

  #[Test]
  public function testProcessReturnsLoginRequiredWhenUserMissing(): void
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

    $authorizationServer = $this->createAuthorizationServerMock(
      authRequest: $this->createAuthorizationRequest(),
    );
    $authorizationServer->expects(self::never())->method('completeAuthorizationRequest');

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock(null),
      queryBus: $this->createStub(QueryBusPort::class),
      requestStack: $requestStack,
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

    self::assertInstanceOf(JsonResponse::class, $response);
    self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
  }

  #[Test]
  public function testProcessReturnsLoginRequiredWhenSelectAccountPrompt(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
        'prompt' => 'select_account',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authorizationServer = $this->createAuthorizationServerMock(
      authRequest: $this->createAuthorizationRequest(),
    );
    $authorizationServer->expects(self::never())->method('completeAuthorizationRequest');

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($this->createSecurityUser()),
      queryBus: $this->createStub(QueryBusPort::class),
      requestStack: $requestStack,
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

    self::assertInstanceOf(JsonResponse::class, $response);
    self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
  }

  #[Test]
  public function testProcessThrowsWhenUserIdEmpty(): void
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

    $authorizationServer = $this->createAuthorizationServerMock(
      authRequest: $this->createAuthorizationRequest(),
    );

    $securityUser = new SecurityUser(
      id: '',
      email: 'user@example.com',
      password: 'hashed',
      roles: ['ROLE_USER'],
      scopes: ['openid'],
      isActive: true,
    );

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new CheckConsentResult(
        hasConsent: true,
        grantedScopes: ['openid'],
        missingScopes: [],
        requiresConsentScreen: false,
      ));

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($securityUser),
      queryBus: $queryBus,
      requestStack: $requestStack,
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->provide(operation: $this->createStub(Operation::class));
  }

  #[Test]
  public function testProcessReturnsErrorWhenAuthorizationRequestThrowsOAuthException(): void
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

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willThrowException(OAuthServerException::invalidRequest('client_id'));

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($this->createSecurityUser()),
      queryBus: $this->createStub(QueryBusPort::class),
      requestStack: $requestStack,
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

    self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
  }

  #[Test]
  public function testProcessThrowsBadRequestOnUnexpectedAuthorizationException(): void
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

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willThrowException(new RuntimeException('boom'));

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($this->createSecurityUser()),
      queryBus: $this->createStub(QueryBusPort::class),
      requestStack: $requestStack,
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->provide(operation: $this->createStub(Operation::class));
  }

  #[Test]
  public function testProcessStoresNonceFromQueryResponse(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
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
      ->willReturn(new Psr7Response(302, ['Location' => 'https://client.example.com/callback?code=query-code']));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new CheckConsentResult(
        hasConsent: true,
        grantedScopes: ['openid'],
        missingScopes: [],
        requiresConsentScreen: false,
      ));

    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::once())
      ->method('updateNonce')
      ->with('query-code', 'nonce-value');

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($this->createSecurityUser()),
      queryBus: $queryBus,
      requestStack: $requestStack,
      authCodeRepository: $authCodeRepository,
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $processor->provide(operation: $this->createStub(Operation::class));
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
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

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
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

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
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

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
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

    self::assertInstanceOf(expected: Response::class, actual: $response);
  }

  #[Test]
  public function testProcessUsesPostRequestAndNormalizesParams(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'POST',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
        'code_challenge_method' => 'S256',
        'state' => '',
        'extra' => ['not-string'],
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
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->process(
      data: null,
      operation: $this->createStub(Operation::class),
    );

    self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
  }

  #[Test]
  public function testProcessAllowsNonCodeResponseType(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'token',
        'client_id' => 'client-123',
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
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

    self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
  }

  #[Test]
  public function testProcessReturnsLoginRequiredWhenMaxAgeZero(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
        'max_age' => '0',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authorizationServer = $this->createAuthorizationServerMock(
      authRequest: $this->createAuthorizationRequest(),
    );

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

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
        authTime: new DateTimeImmutable(),
      ));

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($this->createSecurityUser()),
      queryBus: $queryBus,
      requestStack: $requestStack,
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $oidcUserProvider,
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

    self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
  }

  #[Test]
  public function testProcessReturnsLoginRequiredWhenAuthTimeMissing(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
        'max_age' => '10',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authorizationServer = $this->createAuthorizationServerMock(
      authRequest: $this->createAuthorizationRequest(),
    );

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

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
        authTime: null,
      ));

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($this->createSecurityUser()),
      queryBus: $queryBus,
      requestStack: $requestStack,
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $oidcUserProvider,
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

    self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
  }

  #[Test]
  public function testProcessReturnsLoginRequiredWhenUserIdEmptyWithMaxAge(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
        'max_age' => '10',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $authorizationServer = $this->createAuthorizationServerMock(
      authRequest: $this->createAuthorizationRequest(),
    );

    $securityUser = new SecurityUser(
      id: '',
      email: 'user@example.com',
      password: 'hashed',
      roles: ['ROLE_USER'],
      scopes: ['openid'],
      isActive: true,
    );

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($securityUser),
      queryBus: $this->createStub(QueryBusPort::class),
      requestStack: $requestStack,
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $response = $processor->provide(operation: $this->createStub(Operation::class));

    self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
  }

  #[Test]
  public function testProcessDoesNotStoreNonceWhenCodeMissing(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
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
      ->willReturn(new Psr7Response(200, ['Location' => 'https://client.example.com/callback?state=abc']));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
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
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $processor->provide(operation: $this->createStub(Operation::class));
  }

  #[Test]
  public function testProcessSkipsInvalidLocation(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
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
      ->willReturn(new Psr7Response(200, ['Location' => 'http://']));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
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
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $processor->provide(operation: $this->createStub(Operation::class));
  }

  #[Test]
  public function testProcessSkipsEmptyCodeFromLocation(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
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
      ->willReturn(new Psr7Response(200, ['Location' => 'https://client.example.com/callback?code=']));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
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
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $processor->provide(operation: $this->createStub(Operation::class));
  }

  #[Test]
  public function testProcessStoresNonceFromFormPostValueBeforeName(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
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
      ->willReturn(new Psr7Response(200, [], $this->createStream('<input value="form-code" name="code" />', seekable: false)));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
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
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $processor->provide(operation: $this->createStub(Operation::class));
  }

  #[Test]
  public function testProcessStoresNonceFromBodyQueryParam(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
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
      ->willReturn(new Psr7Response(200, [], 'code=body-code%2Bvalue'));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new CheckConsentResult(
        hasConsent: true,
        grantedScopes: ['openid'],
        missingScopes: [],
        requiresConsentScreen: false,
      ));

    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::once())
      ->method('updateNonce')
      ->with('body-code+value', 'nonce-value');

    $processor = new AuthorizeProcessor(
      authorizationServer: $authorizationServer,
      security: $this->createSecurityMock($this->createSecurityUser()),
      queryBus: $queryBus,
      requestStack: $requestStack,
      authCodeRepository: $authCodeRepository,
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $processor->provide(operation: $this->createStub(Operation::class));
  }

  #[Test]
  public function testProcessSkipsUnreadableBody(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
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
      ->willReturn(new Psr7Response(200, [], $this->createStream('ignored', readable: false)));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
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
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $processor->provide(operation: $this->createStub(Operation::class));
  }

  #[Test]
  public function testProcessSkipsBodyReadException(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'response_type' => 'code',
        'code_challenge' => 'challenge',
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
      ->willReturn(new Psr7Response(200, [], $this->createStream('ignored', throwOnRead: true)));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
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
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
    );

    $processor->provide(operation: $this->createStub(Operation::class));
  }

  #[Test]
  public function testProvideThrowsTooManyRequestsWhenRateLimited(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/authorize',
      method: 'GET',
      parameters: [
        'client_id' => 'client-123',
      ],
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create($this->createRateLimitKey('client-123', '127.0.0.1'))->consume();

    $processor = new AuthorizeProcessor(
      authorizationServer: $this->createStub(AuthorizationServer::class),
      security: $this->createSecurityMock(null),
      queryBus: $this->createStub(QueryBusPort::class),
      requestStack: $requestStack,
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
      rateLimiter: $rateLimiter,
    );

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->provide(operation: $this->createStub(Operation::class));
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
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    return $security;
  }

  private function createRateLimiterFactory(int $limit = 10): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'oauth_authorize',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
  }

  private function createRateLimitKey(string $clientId, string $ipAddress): string
  {
    return sprintf(
      'oauth_authorize_%s_%s',
      substr(hash('sha256', $clientId), 0, 16),
      substr(hash('sha256', $ipAddress), 0, 16),
    );
  }

  private function createStream(
    string $contents,
    bool $readable = true,
    bool $seekable = true,
    bool $throwOnRead = false,
  ): StreamInterface {
    return new class ($contents, $readable, $seekable, $throwOnRead) implements StreamInterface {
      private int $position = 0;

      public function __construct(
        private string $contents,
        private bool $readable,
        private bool $seekable,
        private bool $throwOnRead,
      ) {
      }

      public function __toString(): string
      {
        return $this->contents;
      }

      public function close(): void
      {
      }

      public function detach()
      {
        $resource = null;

        return $resource;
      }

      public function getSize(): int
      {
        return strlen($this->contents);
      }

      public function tell(): int
      {
        return $this->position;
      }

      public function eof(): bool
      {
        return $this->position >= strlen($this->contents);
      }

      public function isSeekable(): bool
      {
        return $this->seekable;
      }

      public function seek($offset, $whence = SEEK_SET): void
      {
        if (!$this->seekable) {
          throw new RuntimeException('Stream is not seekable');
        }

        if (SEEK_SET === $whence) {
          $this->position = (int) $offset;
        } elseif (SEEK_CUR === $whence) {
          $this->position += (int) $offset;
        } elseif (SEEK_END === $whence) {
          $this->position = strlen($this->contents) + (int) $offset;
        }
      }

      public function rewind(): void
      {
        $this->seek(0);
      }

      public function isWritable(): bool
      {
        return false;
      }

      public function write($string): int
      {
        throw new RuntimeException('Stream is not writable');
      }

      public function isReadable(): bool
      {
        return $this->readable;
      }

      public function read($length): string
      {
        $chunk = substr($this->contents, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
      }

      public function getContents(): string
      {
        if ($this->throwOnRead) {
          throw new RuntimeException('Read failed');
        }

        $contents = substr($this->contents, $this->position);
        $this->position = strlen($this->contents);

        return $contents;
      }

      public function getMetadata($key = null)
      {

      }
    };
  }
  // #endregion
}
