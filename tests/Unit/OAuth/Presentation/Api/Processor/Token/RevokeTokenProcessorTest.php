<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Processor\Token;

use ApiPlatform\Metadata\Operation;
use OAuth\Application\UseCase\Command\Token\RevokeToken\RevokeTokenCommand;
use OAuth\Domain\Exception\Token\AuthorizationException;
use OAuth\Presentation\Api\Dto\Input\Token\TokenRevocationInput;
use OAuth\Presentation\Api\Processor\Token\RevokeTokenProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Test RevokeTokenProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RevokeTokenProcessor::class)]
final class RevokeTokenProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenDataInvalid(): void
  {
    $processor = new RevokeTokenProcessor(
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
  public function testProcessThrowsWhenTokenMissing(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/oauth2/revoke', 'POST'));

    $input = new TokenRevocationInput();
    $input->token = null;

    $processor = new RevokeTokenProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
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
  public function testProcessDispatchesCommand(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/oauth2/revoke', 'POST'));

    $input = new TokenRevocationInput();
    $input->token = 'refresh-token';
    $input->tokenTypeHint = 'refresh_token';

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (RevokeTokenCommand $command): bool => 'refresh-token' === $command->token
          && 'refresh_token' === $command->tokenTypeHint,
      ));

    $processor = new RevokeTokenProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $response = $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
    );

    self::assertInstanceOf(JsonResponse::class, $response);
    self::assertSame(200, $response->getStatusCode());
  }

  #[Test]
  public function testProcessThrowsWhenRateLimited(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/oauth2/revoke', 'POST'));

    $input = new TokenRevocationInput();
    $input->token = 'refresh-token';

    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create('127.0.0.1')->consume();

    $processor = new RevokeTokenProcessor(
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

  private function createRateLimiterFactory(string $id = 'oauth_revocation', int $limit = 10): RateLimiterFactory
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
