<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Processor\Consent;

use ApiPlatform\Metadata\Operation;
use Auth\Infrastructure\Security\User\SecurityUser;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Nyholm\Psr7\Response as Psr7Response;
use OAuth\Application\Port\Outbound\Token\AuthCodeRepositoryPort;
use OAuth\Application\UseCase\Command\Consent\GrantConsent\{GrantConsentCommand, GrantConsentResult};
use OAuth\Infrastructure\OAuth2\League\Entity\User as LeagueUser;
use OAuth\Presentation\Api\Dto\Input\Consent\GrantConsentInput;
use OAuth\Presentation\Api\Processor\Consent\GrantConsentProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use ReflectionMethod;
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{JsonResponse, Response};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, TooManyRequestsHttpException, UnauthorizedHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function hash;
use function sprintf;
use function strlen;
use function substr;

use const SEEK_CUR;
use const SEEK_END;
use const SEEK_SET;

/**
 * Test GrantConsentProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: GrantConsentProcessor::class)]
final class GrantConsentProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenDataInvalid(): void
  {
    $processor = new GrantConsentProcessor(
      authorizationServer: $this->createStub(AuthorizationServer::class),
      commandBus: $this->createStub(CommandBusPort::class),
      security: $this->createStub(Security::class),
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: 'invalid',
      operation: $this->createStub(Operation::class),
    );
  }

  #[Test]
  public function testProcessThrowsWhenUserMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new GrantConsentProcessor(
      authorizationServer: $this->createStub(AuthorizationServer::class),
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
    );

    $this->expectException(UnauthorizedHttpException::class);

    $processor->process(
      data: $this->createInput(approved: false),
      operation: $this->createStub(Operation::class),
    );
  }

  #[Test]
  public function testProcessDispatchesConsentAndStoresNonce(): void
  {
    $input = $this->createInput(approved: true);

    $securityUser = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hashed',
      roles: ['ROLE_USER'],
      scopes: ['openid'],
      isActive: true,
    );

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($securityUser);

    $authorizationRequest = $this->createMock(AuthorizationRequestInterface::class);
    $authorizationRequest->expects(self::once())
      ->method('setUser')
      ->with(self::isInstanceOf(LeagueUser::class));
    $authorizationRequest->expects(self::once())
      ->method('setAuthorizationApproved')
      ->with(true);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authorizationRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->with($authorizationRequest, self::isInstanceOf(\Psr\Http\Message\ResponseInterface::class))
      ->willReturn(new Psr7Response(302, ['Location' => 'https://client.example.com/callback?code=auth-code']));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (GrantConsentCommand $command): bool => 'user-123' === $command->userId
          && 'client-123' === $command->clientId
          && $command->scopes === ['openid', 'profile'],
      ))
      ->willReturn(new GrantConsentResult(
        consentId: 'consent-123',
        isNew: true,
      ));

    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::once())
      ->method('updateNonce')
      ->with('auth-code', 'nonce-value');

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $commandBus,
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $authCodeRepository,
    );

    $response = $processor->process(
      data: $input,
      operation: $this->createStub(Operation::class),
    );

    self::assertInstanceOf(Response::class, $response);
    self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
  }

  #[Test]
  public function testProcessReturnsErrorWhenAuthorizationRequestFails(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willThrowException(OAuthServerException::invalidRequest('client_id'));

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
    );

    $response = $processor->process(
      data: $this->createInput(approved: false),
      operation: $this->createStub(Operation::class),
    );

    self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
  }

  #[Test]
  public function testProcessReturnsJsonErrorOnUnexpectedException(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willThrowException(new RuntimeException('boom'));

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
    );

    $response = $processor->process(
      data: $this->createInput(approved: false),
      operation: $this->createStub(Operation::class),
    );

    self::assertInstanceOf(JsonResponse::class, $response);
    self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
  }

  #[Test]
  public function testProcessReturnsBadRequestWhenUserIdEmpty(): void
  {
    $securityUser = new SecurityUser(
      id: '',
      email: 'user@example.com',
      password: 'hashed',
      roles: ['ROLE_USER'],
      scopes: ['openid'],
      isActive: true,
    );

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($securityUser);

    $authorizationRequest = $this->createStub(AuthorizationRequestInterface::class);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authorizationRequest);

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
    );

    $response = $processor->process(
      data: $this->createInput(approved: false),
      operation: $this->createStub(Operation::class),
    );

    self::assertInstanceOf(JsonResponse::class, $response);
    self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
  }

  #[Test]
  public function testProcessDoesNotDispatchWhenNotApproved(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $authorizationRequest = $this->createStub(AuthorizationRequestInterface::class);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authorizationRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->willReturn(new Psr7Response(302, ['Location' => 'https://client.example.com/callback?code=auth-code']));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $commandBus,
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
    );

    $response = $processor->process(
      data: $this->createInput(approved: false),
      operation: $this->createStub(Operation::class),
    );

    self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
  }

  #[Test]
  public function testProcessStoresNonceFromFormPostBody(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $authorizationRequest = $this->createStub(AuthorizationRequestInterface::class);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authorizationRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->willReturn(new Psr7Response(200, [], '<input name="code" value="form-code" />'));
    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::once())
      ->method('updateNonce')
      ->with('form-code', 'nonce-value');

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $authCodeRepository,
    );

    $response = $processor->process(
      data: $this->createInput(approved: false),
      operation: $this->createStub(Operation::class),
    );

    self::assertInstanceOf(Response::class, $response);
  }

  #[Test]
  public function testProcessReturnsErrorWhenCompletionFails(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $authorizationRequest = $this->createStub(AuthorizationRequestInterface::class);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authorizationRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->willThrowException(OAuthServerException::invalidRequest('client_id'));

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
    );

    $response = $processor->process(
      data: $this->createInput(approved: false),
      operation: $this->createStub(Operation::class),
    );

    self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
  }

  #[Test]
  public function testProcessDispatchesEmptyScopesWhenScopeBlank(): void
  {
    $input = $this->createInput(approved: true);
    $input->scope = ' ';
    $input->state = null;
    $input->nonce = null;

    $securityUser = $this->createSecurityUser();

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($securityUser);

    $authorizationRequest = $this->createStub(AuthorizationRequestInterface::class);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authorizationRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->willReturn(new Psr7Response(302, ['Location' => 'https://client.example.com/callback?code=auth-code']));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (GrantConsentCommand $command): bool => [] === $command->scopes,
      ))
      ->willReturn(new GrantConsentResult(
        consentId: 'consent-456',
        isNew: true,
      ));

    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::never())->method('updateNonce');

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $commandBus,
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $authCodeRepository,
    );

    $response = $processor->process(
      data: $input,
      operation: $this->createStub(Operation::class),
    );

    self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
  }

  #[Test]
  public function testProcessDoesNotStoreNonceWhenCodeMissing(): void
  {
    $input = $this->createInput(approved: false);
    $input->nonce = 'nonce-value';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $authorizationRequest = $this->createStub(AuthorizationRequestInterface::class);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authorizationRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->willReturn(new Psr7Response(200, ['Location' => 'https://client.example.com/callback']));

    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::never())->method('updateNonce');

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $authCodeRepository,
    );

    $processor->process(
      data: $input,
      operation: $this->createStub(Operation::class),
    );
  }

  #[Test]
  public function testProcessSkipsInvalidLocationAndEmptyCode(): void
  {
    $input = $this->createInput(approved: false);
    $input->nonce = 'nonce-value';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $authorizationRequest = $this->createStub(AuthorizationRequestInterface::class);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authorizationRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->willReturn(new Psr7Response(302, ['Location' => 'http://']));

    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::never())->method('updateNonce');

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $authCodeRepository,
    );

    $processor->process(
      data: $input,
      operation: $this->createStub(Operation::class),
    );
  }

  #[Test]
  public function testProcessSkipsEmptyCodeFromLocation(): void
  {
    $input = $this->createInput(approved: false);
    $input->nonce = 'nonce-value';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $authorizationRequest = $this->createStub(AuthorizationRequestInterface::class);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authorizationRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->willReturn(new Psr7Response(302, ['Location' => 'https://client.example.com/callback?code=']));

    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::never())->method('updateNonce');

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $authCodeRepository,
    );

    $processor->process(
      data: $input,
      operation: $this->createStub(Operation::class),
    );
  }

  #[Test]
  public function testProcessStoresNonceFromFormPostValueBeforeName(): void
  {
    $input = $this->createInput(approved: false);
    $input->nonce = 'nonce-value';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $authorizationRequest = $this->createStub(AuthorizationRequestInterface::class);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authorizationRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->willReturn(new Psr7Response(200, [], $this->createStream('<input value="form-code" name="code" />', seekable: false)));

    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::once())
      ->method('updateNonce')
      ->with('form-code', 'nonce-value');

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $authCodeRepository,
    );

    $processor->process(
      data: $input,
      operation: $this->createStub(Operation::class),
    );
  }

  #[Test]
  public function testProcessStoresNonceFromBodyQueryParam(): void
  {
    $input = $this->createInput(approved: false);
    $input->nonce = 'nonce-value';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $authorizationRequest = $this->createStub(AuthorizationRequestInterface::class);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authorizationRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->willReturn(new Psr7Response(200, [], 'code=form-code%2Bvalue'));

    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::once())
      ->method('updateNonce')
      ->with('form-code+value', 'nonce-value');

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $authCodeRepository,
    );

    $processor->process(
      data: $input,
      operation: $this->createStub(Operation::class),
    );
  }

  #[Test]
  public function testProcessDoesNotStoreNonceWhenBodyUnreadable(): void
  {
    $input = $this->createInput(approved: false);
    $input->nonce = 'nonce-value';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $authorizationRequest = $this->createStub(AuthorizationRequestInterface::class);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authorizationRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->willReturn(new Psr7Response(200, [], $this->createStream('ignored', readable: false)));

    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::never())->method('updateNonce');

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $authCodeRepository,
    );

    $processor->process(
      data: $input,
      operation: $this->createStub(Operation::class),
    );
  }

  #[Test]
  public function testProcessDoesNotStoreNonceWhenBodyReadFails(): void
  {
    $input = $this->createInput(approved: false);
    $input->nonce = 'nonce-value';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $authorizationRequest = $this->createStub(AuthorizationRequestInterface::class);

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willReturn($authorizationRequest);
    $authorizationServer->expects(self::once())
      ->method('completeAuthorizationRequest')
      ->willReturn(new Psr7Response(200, [], $this->createStream('ignored', throwOnRead: true)));

    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $authCodeRepository->expects(self::never())->method('updateNonce');

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $authCodeRepository,
    );

    $processor->process(
      data: $input,
      operation: $this->createStub(Operation::class),
    );
  }

  #[Test]
  public function testExtractCodeFromFormPostBodyReturnsNullWhenEmpty(): void
  {
    $processor = new GrantConsentProcessor(
      authorizationServer: $this->createStub(AuthorizationServer::class),
      commandBus: $this->createStub(CommandBusPort::class),
      security: $this->createStub(Security::class),
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
    );

    $method = new ReflectionMethod(GrantConsentProcessor::class, 'extractCodeFromFormPostBody');
    $method->setAccessible(true);

    self::assertNull($method->invoke($processor, ''));
  }

  #[Test]
  public function testExtractCodeFromFormPostBodyReturnsNullWhenCodeMissing(): void
  {
    $processor = new GrantConsentProcessor(
      authorizationServer: $this->createStub(AuthorizationServer::class),
      commandBus: $this->createStub(CommandBusPort::class),
      security: $this->createStub(Security::class),
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
    );

    $method = new ReflectionMethod(GrantConsentProcessor::class, 'extractCodeFromFormPostBody');
    $method->setAccessible(true);

    self::assertNull($method->invoke($processor, '<input name=\"state\" value=\"xyz\" />'));
  }

  #[Test]
  public function testProcessThrowsTooManyRequestsWhenRateLimited(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create($this->createRateLimitKey('user-123', 'client-123'))->consume();

    $processor = new GrantConsentProcessor(
      authorizationServer: $this->createStub(AuthorizationServer::class),
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      rateLimiter: $rateLimiter,
    );

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process(
      data: $this->createInput(approved: true),
      operation: $this->createStub(Operation::class),
    );
  }

  #[Test]
  public function testProcessProceedsWhenTheRateLimitAcceptsTheRequest(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('validateAuthorizationRequest')
      ->willThrowException(OAuthServerException::invalidRequest('client_id'));

    $processor = new GrantConsentProcessor(
      authorizationServer: $authorizationServer,
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      rateLimiter: $this->createRateLimiterFactory(limit: 10),
    );

    // A configured-but-unexhausted limiter must let the request through: the
    // 400 proves enforceRateLimit() returned early instead of raising a 429.
    $response = $processor->process(
      data: $this->createInput(approved: false),
      operation: $this->createStub(Operation::class),
    );

    self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
  }

  #[Test]
  public function testRevokedIssuingSessionCannotPersistConsentOrCreateCode(): void
  {
    $server = $this->createMock(AuthorizationServer::class);
    $server->method('validateAuthorizationRequest')->willReturn($this->createStub(AuthorizationRequestInterface::class));
    $server->expects(self::never())->method('completeAuthorizationRequest');
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());
    $locked = false;
    $lifecycle = $this->createMock(\OAuth\Application\Port\Outbound\Token\GrantLifecyclePort::class);
    $lifecycle->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());
    $lifecycle->expects(self::once())->method('lockUser')->with('user-123')->willReturnCallback(static function () use (&$locked): void { $locked = true; });
    $sessions = $this->createMock(\Auth\Application\Port\Outbound\SessionStatusPort::class);
    $sessions->expects(self::once())->method('activeSessionId')->willReturnCallback(static function () use (&$locked): ?string {
      self::assertTrue($locked);

      return null;
    });
    $processor = new GrantConsentProcessor(
      authorizationServer: $server,
      commandBus: $commands,
      security: $security,
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      scopeRepository: $this->createStub(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class),
      grantCompletion: new \OAuth\Presentation\Api\Service\AuthorizationGrantCompletion($lifecycle, $sessions),
      requestStack: $this->requestStack(),
    );

    self::assertSame(401, $processor->process($this->createInput(approved: true), $this->createStub(Operation::class))->getStatusCode());
  }

  #[Test]
  public function testDisallowedScopeCannotPersistConsentOrCreateCode(): void
  {
    $server = $this->createMock(AuthorizationServer::class);
    $server->method('validateAuthorizationRequest')->willReturn($this->createStub(AuthorizationRequestInterface::class));
    $server->expects(self::never())->method('completeAuthorizationRequest');
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());
    $scopes = $this->createMock(\League\OAuth2\Server\Repositories\ScopeRepositoryInterface::class);
    $scopes->expects(self::once())->method('finalizeScopes')->willThrowException(OAuthServerException::invalidScope('ADMIN'));
    $processor = new GrantConsentProcessor(
      authorizationServer: $server,
      commandBus: $commands,
      security: $security,
      authCodeRepository: $this->createStub(AuthCodeRepositoryPort::class),
      scopeRepository: $scopes,
      grantCompletion: $this->grantCompletion(),
      requestStack: $this->requestStack(),
    );

    self::assertSame(400, $processor->process($this->createInput(approved: true), $this->createStub(Operation::class))->getStatusCode());
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

  private function createInput(bool $approved): GrantConsentInput
  {
    $input = new GrantConsentInput();
    $input->clientId = 'client-123';
    $input->responseType = 'code';
    $input->redirectUri = 'https://client.example.com/callback';
    $input->scope = 'openid profile openid';
    $input->state = 'state-123';
    $input->codeChallenge = 'challenge';
    $input->codeChallengeMethod = 'S256';
    $input->nonce = 'nonce-value';
    $input->approved = $approved;

    return $input;
  }

  private function createRateLimiterFactory(int $limit = 10): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'oauth_consent_grant',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
  }

  private function createRateLimitKey(string $userId, string $clientId): string
  {
    return sprintf(
      'oauth_consent_grant_%s_%s',
      substr(hash('sha256', $userId), 0, 16),
      substr(hash('sha256', $clientId), 0, 16),
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

  private function grantCompletion(): \OAuth\Presentation\Api\Service\AuthorizationGrantCompletion
  {
    $lifecycle = $this->createStub(\OAuth\Application\Port\Outbound\Token\GrantLifecyclePort::class);
    $lifecycle->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());
    $sessions = $this->createStub(\Auth\Application\Port\Outbound\SessionStatusPort::class);
    $sessions->method('activeSessionId')->willReturn('active-session');

    return new \OAuth\Presentation\Api\Service\AuthorizationGrantCompletion($lifecycle, $sessions);
  }

  private function requestStack(): \Symfony\Component\HttpFoundation\RequestStack
  {
    $request = new \Symfony\Component\HttpFoundation\Request();
    $request->attributes->set('_fireguard_verified_token_id', 'verified-token-id');
    $request->attributes->set('_fireguard_verified_token_use', 'auth_session');
    $requests = new \Symfony\Component\HttpFoundation\RequestStack();
    $requests->push($request);

    return $requests;
  }
  // #endregion
}
