<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Processor\Session;

use ApiPlatform\Metadata\Operation;
use Auth\Presentation\Service\RefreshTokenCookieService;
use OAuth\Application\Port\Outbound\Token\JwtParserPort;
use OAuth\Application\UseCase\Command\Token\RevokeToken\RevokeTokenCommand;
use OAuth\Application\UseCase\Command\Token\RevokeToken\RevokeTokenResult;
use OAuth\Application\UseCase\Query\Client\GetClient\GetClientQuery;
use OAuth\Application\UseCase\Query\Client\GetClient\GetClientResult;
use OAuth\Presentation\Api\Processor\Session\EndSessionProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;

/**
 * Test EndSessionProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: EndSessionProcessor::class)]
final class EndSessionProcessorTest extends TestCase
{
  // #region Methods
  /**
   * Method testProcessReturnsInvalidRequestWhenClientIdMissing.
   *
   * Test that post_logout_redirect_uri without client_id returns invalid_request.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessReturnsInvalidRequestWhenClientIdMissing(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/logout',
      method: 'GET',
      parameters: [
        'post_logout_redirect_uri' => 'https://client.example.com/logout',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $processor = new EndSessionProcessor(
      requestStack: $requestStack,
      commandBus: $commandBus,
      queryBus: $queryBus,
      jwtParser: $this->createMock(JwtParserPort::class),
      cookieService: $this->createCookieService(),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: JsonResponse::class, actual: $response);
    self::assertSame(expected: Response::HTTP_BAD_REQUEST, actual: $response->getStatusCode());

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertSame(expected: 'invalid_request', actual: $body['error'] ?? null);

    $cookie = $request->attributes->get('_refresh_token_cookie');
    self::assertInstanceOf(expected: Cookie::class, actual: $cookie);
  }

  /**
   * Method testProcessRedirectsWhenPostLogoutUriAllowed.
   *
   * Test that a valid post_logout_redirect_uri returns a redirect.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessRedirectsWhenPostLogoutUriAllowed(): void
  {
    $postLogoutUri = 'https://client.example.com/logout';

    $request = Request::create(
      uri: '/api/oauth2/logout',
      method: 'GET',
      parameters: [
        'post_logout_redirect_uri' => $postLogoutUri,
        'client_id' => 'client-123',
        'state' => 'logout-state',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetClientQuery::class))
      ->willReturn($this->createClientResult($postLogoutUri));

    $processor = new EndSessionProcessor(
      requestStack: $requestStack,
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $queryBus,
      jwtParser: $this->createMock(JwtParserPort::class),
      cookieService: $this->createCookieService(),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: RedirectResponse::class, actual: $response);
    self::assertSame(
      expected: $postLogoutUri . '?state=logout-state',
      actual: $response->headers->get('Location'),
    );
  }

  /**
   * Method testProcessUsesIdTokenHintToResolveClientId.
   *
   * Test that id_token_hint is used when client_id is missing.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessUsesIdTokenHintToResolveClientId(): void
  {
    $postLogoutUri = 'https://client.example.com/logout';

    $request = Request::create(
      uri: '/api/oauth2/logout',
      method: 'GET',
      parameters: [
        'post_logout_redirect_uri' => $postLogoutUri,
        'id_token_hint' => 'jwt-token',
        'state' => 'logout-state',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->with('jwt-token')
      ->willReturn(true);
    $jwtParser->expects(self::once())
      ->method('parse')
      ->with('jwt-token')
      ->willReturn(['aud' => 'client-456']);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetClientQuery::class))
      ->willReturn($this->createClientResult($postLogoutUri));

    $processor = new EndSessionProcessor(
      requestStack: $requestStack,
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $queryBus,
      jwtParser: $jwtParser,
      cookieService: $this->createCookieService(),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: RedirectResponse::class, actual: $response);
    self::assertSame(
      expected: $postLogoutUri . '?state=logout-state',
      actual: $response->headers->get('Location'),
    );
  }

  /**
   * Method testProcessReturnsInvalidRequestWhenIdTokenHintInvalid.
   *
   * Test that an invalid id_token_hint returns invalid_request.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessReturnsInvalidRequestWhenIdTokenHintInvalid(): void
  {
    $postLogoutUri = 'https://client.example.com/logout';

    $request = Request::create(
      uri: '/api/oauth2/logout',
      method: 'GET',
      parameters: [
        'post_logout_redirect_uri' => $postLogoutUri,
        'client_id' => 'client-123',
        'id_token_hint' => 'jwt-token',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->with('jwt-token')
      ->willReturn(false);
    $jwtParser->expects(self::never())
      ->method('parse');

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new EndSessionProcessor(
      requestStack: $requestStack,
      commandBus: $commandBus,
      queryBus: $queryBus,
      jwtParser: $jwtParser,
      cookieService: $this->createCookieService(),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: JsonResponse::class, actual: $response);
    self::assertSame(expected: Response::HTTP_BAD_REQUEST, actual: $response->getStatusCode());

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertSame(expected: 'invalid_request', actual: $body['error'] ?? null);
  }

  /**
   * Method testProcessReturnsInvalidRequestWhenClientIdMismatch.
   *
   * Test that a mismatched client_id and id_token_hint returns invalid_request.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessReturnsInvalidRequestWhenClientIdMismatch(): void
  {
    $postLogoutUri = 'https://client.example.com/logout';

    $request = Request::create(
      uri: '/api/oauth2/logout',
      method: 'GET',
      parameters: [
        'post_logout_redirect_uri' => $postLogoutUri,
        'client_id' => 'client-123',
        'id_token_hint' => 'jwt-token',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->with('jwt-token')
      ->willReturn(true);
    $jwtParser->expects(self::once())
      ->method('parse')
      ->with('jwt-token')
      ->willReturn(['aud' => 'client-456']);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new EndSessionProcessor(
      requestStack: $requestStack,
      commandBus: $commandBus,
      queryBus: $queryBus,
      jwtParser: $jwtParser,
      cookieService: $this->createCookieService(),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: JsonResponse::class, actual: $response);
    self::assertSame(expected: Response::HTTP_BAD_REQUEST, actual: $response->getStatusCode());

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertSame(expected: 'invalid_request', actual: $body['error'] ?? null);
    self::assertSame(
      expected: 'client_id does not match id_token_hint.',
      actual: $body['error_description'] ?? null,
    );
  }

  /**
   * Method testProcessReturnsInvalidRequestWhenPostLogoutUriNotAllowed.
   *
   * Test that an unregistered post_logout_redirect_uri returns invalid_request.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessReturnsInvalidRequestWhenPostLogoutUriNotAllowed(): void
  {
    $request = Request::create(
      uri: '/api/oauth2/logout',
      method: 'GET',
      parameters: [
        'post_logout_redirect_uri' => 'https://client.example.com/logout',
        'client_id' => 'client-123',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetClientQuery::class))
      ->willReturn(new GetClientResult(
        id: 'client-123',
        name: 'Test Client',
        redirectUris: ['https://client.example.com/other'],
        grantTypes: ['authorization_code'],
        scopes: ['openid'],
        isActive: true,
        createdAt: '2024-01-01T00:00:00+00:00',
      ));

    $processor = new EndSessionProcessor(
      requestStack: $requestStack,
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $queryBus,
      jwtParser: $this->createMock(JwtParserPort::class),
      cookieService: $this->createCookieService(),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: JsonResponse::class, actual: $response);
    self::assertSame(expected: Response::HTTP_BAD_REQUEST, actual: $response->getStatusCode());

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertSame(expected: 'invalid_request', actual: $body['error'] ?? null);
  }

  /**
   * Method testProcessUsesIdTokenHintAudienceArray.
   *
   * Test that id_token_hint with audience array resolves a client.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessUsesIdTokenHintAudienceArray(): void
  {
    $postLogoutUri = 'https://client.example.com/logout';

    $request = Request::create(
      uri: '/api/oauth2/logout',
      method: 'GET',
      parameters: [
        'post_logout_redirect_uri' => $postLogoutUri,
        'id_token_hint' => 'jwt-token',
        'state' => 'logout-state',
      ],
    );

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->with('jwt-token')
      ->willReturn(true);
    $jwtParser->expects(self::once())
      ->method('parse')
      ->with('jwt-token')
      ->willReturn(['aud' => ['client-456', 'client-789']]);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetClientQuery::class))
      ->willReturn($this->createClientResult($postLogoutUri));

    $processor = new EndSessionProcessor(
      requestStack: $requestStack,
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $queryBus,
      jwtParser: $jwtParser,
      cookieService: $this->createCookieService(),
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: RedirectResponse::class, actual: $response);
    self::assertSame(
      expected: $postLogoutUri . '?state=logout-state',
      actual: $response->headers->get('Location'),
    );
  }

  /**
   * Method testProcessReturnsJsonWhenNoRedirectUri.
   *
   * Test that without post_logout_redirect_uri a
   * JSON response is returned.
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessReturnsJsonWhenNoRedirectUri(): void
  {
    $cookieService = $this->createCookieService();

    $request = Request::create('/api/oauth2/logout', 'GET');
    $request->cookies->set($cookieService->getCookieName(), 'refresh-token');
    $request->headers->set('Authorization', 'Bearer access-token');

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $commandBus = $this->createMock(CommandBusPort::class);
    $callCount = 0;
    $commandBus->expects(self::exactly(2))
      ->method('dispatch')
      ->willReturnCallback(function (RevokeTokenCommand $command) use (&$callCount): RevokeTokenResult {
        if (0 === $callCount) {
          self::assertSame(RevokeTokenCommand::HINT_REFRESH_TOKEN, $command->tokenTypeHint);
          self::assertSame('refresh-token', $command->token);
        } else {
          self::assertSame(RevokeTokenCommand::HINT_ACCESS_TOKEN, $command->tokenTypeHint);
          self::assertSame('access-token', $command->token);
        }
        ++$callCount;

        return new RevokeTokenResult(revoked: true);
      });

    $processor = new EndSessionProcessor(
      requestStack: $requestStack,
      commandBus: $commandBus,
      queryBus: $this->createMock(QueryBusPort::class),
      jwtParser: $this->createMock(JwtParserPort::class),
      cookieService: $cookieService,
    );

    $response = $processor->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(expected: JsonResponse::class, actual: $response);
    self::assertSame(expected: Response::HTTP_OK, actual: $response->getStatusCode());

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertTrue($body['logged_out'] ?? false);

    $cookie = $request->attributes->get('_refresh_token_cookie');
    self::assertInstanceOf(expected: Cookie::class, actual: $cookie);
  }

  private function createClientResult(string $postLogoutUri): GetClientResult
  {
    return new GetClientResult(
      id: 'client-123',
      name: 'Test Client',
      redirectUris: [$postLogoutUri],
      grantTypes: ['authorization_code'],
      scopes: ['openid'],
      isActive: true,
      createdAt: '2024-01-01T00:00:00+00:00',
    );
  }

  private function createCookieService(): RefreshTokenCookieService
  {
    return new RefreshTokenCookieService(
      environment: 'test',
      cookieBaseName: 'refresh_token',
      lifetimeShort: 3600,
      lifetimeLong: 7200,
    );
  }
  // #endregion
}
