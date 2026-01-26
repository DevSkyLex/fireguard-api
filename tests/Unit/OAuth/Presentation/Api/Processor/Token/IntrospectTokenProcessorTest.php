<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Processor\Token;

use ApiPlatform\Metadata\Operation;
use OAuth\Application\UseCase\Query\Token\IntrospectToken\IntrospectTokenResult;
use OAuth\Domain\Exception\Token\AuthorizationException;
use OAuth\Presentation\Api\Dto\Input\Token\TokenIntrospectionInput;
use OAuth\Presentation\Api\Dto\Output\Token\TokenIntrospectionOutput;
use OAuth\Presentation\Api\Processor\Token\IntrospectTokenProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Test IntrospectTokenProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: IntrospectTokenProcessor::class)]
final class IntrospectTokenProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenDataInvalid(): void
  {
    $processor = new IntrospectTokenProcessor(
      queryBus: $this->createMock(QueryBusPort::class),
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
  public function testProcessThrowsWhenTokenMissing(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/oauth2/introspect', 'POST'));

    $input = new TokenIntrospectionInput();
    $input->token = null;

    $processor = new IntrospectTokenProcessor(
      queryBus: $this->createMock(QueryBusPort::class),
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
  public function testProcessReturnsOutput(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/oauth2/introspect', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']));

    $input = new TokenIntrospectionInput();
    $input->token = 'access-token';
    $input->tokenTypeHint = 'access_token';

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new IntrospectTokenResult(
        active: true,
        scope: 'openid',
        clientId: 'client-123',
        username: 'user',
        tokenType: 'Bearer',
        exp: 1700000000,
        iat: 1699990000,
        nbf: 1699990000,
        sub: 'user-123',
        aud: 'client-123',
        iss: 'https://auth.example.com',
        jti: 'token-id',
      ));

    $processor = new IntrospectTokenProcessor(
      queryBus: $queryBus,
      requestStack: $requestStack,
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $output = $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
    );

    self::assertInstanceOf(TokenIntrospectionOutput::class, $output);
    self::assertTrue($output->active);
    self::assertSame('client-123', $output->clientId);
    self::assertSame('token-id', $output->jti);
  }

  #[Test]
  public function testProcessThrowsWhenRateLimited(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/oauth2/introspect', 'POST'));

    $input = new TokenIntrospectionInput();
    $input->token = 'access-token';

    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create('127.0.0.1')->consume();

    $processor = new IntrospectTokenProcessor(
      queryBus: $this->createMock(QueryBusPort::class),
      requestStack: $requestStack,
      rateLimiter: $rateLimiter,
    );

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
    );
  }

  private function createRateLimiterFactory(string $id = 'oauth_introspection', int $limit = 10): RateLimiterFactory
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
