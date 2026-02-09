<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Processor\Token;

use ApiPlatform\Metadata\Operation;
use League\OAuth2\Server\Exception\OAuthServerException;
use OAuth\Application\UseCase\Command\Token\IssueToken\{IssueTokenCommand, IssueTokenResult};
use OAuth\Domain\Exception\Token\AuthorizationException;
use OAuth\Presentation\Api\Dto\Input\Token\TokenInput;
use OAuth\Presentation\Api\Dto\Output\Token\TokenOutput;
use OAuth\Presentation\Api\Processor\Token\IssueTokenProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Infrastructure\Exception\MessengerRuntimeException;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Test IssueTokenProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: IssueTokenProcessor::class)]
final class IssueTokenProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenDataInvalid(): void
  {
    $processor = new IssueTokenProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      requestStack: new RequestStack(),
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $this->expectException(AuthorizationException::class);

    $processor->process(
      data: 'invalid',
      operation: $this->createMock(Operation::class),
    );
  }

  #[Test]
  public function testProcessThrowsWhenRequestMissing(): void
  {
    $processor = new IssueTokenProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      requestStack: new RequestStack(),
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $input = new TokenInput();
    $input->grantType = 'client_credentials';
    $input->clientId = 'client-123';
    $input->clientSecret = 'secret';

    $this->expectException(AuthorizationException::class);

    $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
    );
  }

  #[Test]
  public function testProcessReturnsTokenOutput(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/oauth2/token',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    $input = new TokenInput();
    $input->grantType = 'client_credentials';
    $input->clientId = 'client-123';
    $input->clientSecret = 'secret';
    $input->scope = 'openid';

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (IssueTokenCommand $command): bool => 'client-123' === $command->clientId
          && 'client_credentials' === $command->grantType
          && '127.0.0.1' === $command->ipAddress,
      ))
      ->willReturn(new IssueTokenResult(
        accessToken: 'access-token',
        tokenType: 'Bearer',
        expiresIn: 3600,
        refreshToken: 'refresh-token',
        scope: 'openid',
        idToken: null,
      ));

    $processor = new IssueTokenProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $output = $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
    );

    self::assertInstanceOf(TokenOutput::class, $output);
    self::assertSame('access-token', $output->accessToken);
    self::assertSame('Bearer', $output->tokenType);
    self::assertSame(3600, $output->expiresIn);
  }

  #[Test]
  public function testProcessThrowsWhenRateLimited(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/oauth2/token',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    $input = new TokenInput();
    $input->grantType = 'client_credentials';
    $input->clientId = 'client-123';
    $input->clientSecret = 'secret';

    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create('client-123|127.0.0.1')->consume();

    $processor = new IssueTokenProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      requestStack: $requestStack,
      rateLimiter: $rateLimiter,
    );

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
    );
  }

  #[Test]
  public function testProcessRethrowsAuthorizationExceptionFromMessenger(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/oauth2/token',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    $input = new TokenInput();
    $input->grantType = 'client_credentials';
    $input->clientId = 'client-123';
    $input->clientSecret = 'secret';

    $command = new IssueTokenCommand(
      grantType: 'client_credentials',
      clientId: 'client-123',
      clientSecret: 'secret',
    );

    $nested = AuthorizationException::invalidRequest('Invalid grant');
    $handlerFailed = new HandlerFailedException(new Envelope($command), [$nested]);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailed));

    $processor = new IssueTokenProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $this->expectException(AuthorizationException::class);

    $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
    );
  }

  #[Test]
  public function testProcessRethrowsAuthorizationExceptionDirect(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/oauth2/token',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    $input = new TokenInput();
    $input->grantType = 'client_credentials';
    $input->clientId = 'client-123';
    $input->clientSecret = 'secret';

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(AuthorizationException::invalidRequest('Invalid grant'));

    $processor = new IssueTokenProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $this->expectException(AuthorizationException::class);

    $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
    );
  }

  #[Test]
  public function testProcessRethrowsOAuthServerExceptionDirect(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/oauth2/token',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    $input = new TokenInput();
    $input->grantType = 'client_credentials';
    $input->clientId = 'client-123';
    $input->clientSecret = 'secret';

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(OAuthServerException::invalidRequest('client_id'));

    $processor = new IssueTokenProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $this->expectException(OAuthServerException::class);

    $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
    );
  }

  #[Test]
  public function testProcessRethrowsMessengerExceptionPreviousChain(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/oauth2/token',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    $input = new TokenInput();
    $input->grantType = 'client_credentials';
    $input->clientId = 'client-123';
    $input->clientSecret = 'secret';

    $inner = AuthorizationException::invalidRequest('Invalid grant');
    $outer = new RuntimeException('outer', 0, $inner);
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($outer));

    $processor = new IssueTokenProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $this->expectException(AuthorizationException::class);

    $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
    );
  }

  #[Test]
  public function testProcessRethrowsMessengerExceptionWhenUnhandled(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/oauth2/token',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    $input = new TokenInput();
    $input->grantType = 'client_credentials';
    $input->clientId = 'client-123';
    $input->clientSecret = 'secret';

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap(new RuntimeException('outer')));

    $processor = new IssueTokenProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $this->expectException(MessengerRuntimeException::class);

    $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
    );
  }

  #[Test]
  public function testProcessRethrowsAuthorizationExceptionFromThrowableChain(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/oauth2/token',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    $input = new TokenInput();
    $input->grantType = 'client_credentials';
    $input->clientId = 'client-123';
    $input->clientSecret = 'secret';

    $inner = AuthorizationException::invalidRequest('Invalid grant');
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException('outer', 0, $inner));

    $processor = new IssueTokenProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $this->expectException(AuthorizationException::class);

    $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
    );
  }

  #[Test]
  public function testProcessRethrowsAuthorizationExceptionFromDeepChain(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/oauth2/token',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    $input = new TokenInput();
    $input->grantType = 'client_credentials';
    $input->clientId = 'client-123';
    $input->clientSecret = 'secret';

    $inner = AuthorizationException::invalidRequest('Invalid grant');
    $middle = new RuntimeException('middle', 0, $inner);
    $outer = new RuntimeException('outer', 0, $middle);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException($outer);

    $processor = new IssueTokenProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $this->expectException(AuthorizationException::class);

    $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
    );
  }

  #[Test]
  public function testProcessWrapsUnhandledThrowableAsServerError(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/oauth2/token',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    $input = new TokenInput();
    $input->grantType = 'client_credentials';
    $input->clientId = 'client-123';
    $input->clientSecret = 'secret';

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException('boom'));

    $processor = new IssueTokenProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $this->expectException(AuthorizationException::class);
    $this->expectExceptionMessage('Authorization server error.');

    $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
    );
  }

  private function createRateLimiterFactory(string $id = 'oauth_token', int $limit = 10): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => $id,
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
  }
  // #endregion
}
