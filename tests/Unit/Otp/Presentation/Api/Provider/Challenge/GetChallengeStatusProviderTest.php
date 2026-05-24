<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Presentation\Api\Provider\Challenge;

use ApiPlatform\Metadata\Get;
use DateTimeImmutable;
use Otp\Application\Exception\OtpNotFoundException;
use Otp\Application\UseCase\Query\Challenge\GetChallengeStatus\{GetChallengeStatusQuery, GetChallengeStatusResult};
use Otp\Presentation\Api\Dto\Output\Challenge\ChallengeOutput;
use Otp\Presentation\Api\Provider\Challenge\GetChallengeStatusProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use stdClass;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Test GetChallengeStatusProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetChallengeStatusProvider::class)]
final class GetChallengeStatusProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideThrowsWhenTokenMissing(): void
  {
    $provider = new GetChallengeStatusProvider($this->createStub(QueryBusPort::class));

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get());
  }

  #[Test]
  public function testProvideMapsResult(): void
  {
    $result = new GetChallengeStatusResult(
      expiresAt: new DateTimeImmutable('+5 minutes'),
      status: 'pending',
      attemptsRemaining: 4,
      maskedRecipient: 'jo******@example.com',
      createdAt: new DateTimeImmutable('-1 minute'),
      canResendIn: 30,
      purpose: 'login',
      channel: 'email',
      recipient: 'john.doe@example.com',
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetChallengeStatusQuery::class))
      ->willReturn($result);

    $provider = new GetChallengeStatusProvider($queryBus);

    $output = $provider->provide(new Get(), ['token' => 'token-1']);

    self::assertInstanceOf(ChallengeOutput::class, $output);
    self::assertSame('token-1', $output->token);
    self::assertSame('pending', $output->status);
    self::assertSame(30, $output->canResendIn);
  }

  #[Test]
  public function testProvideMapsOtpNotFound(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(OtpNotFoundException::forIdentifier('token-2'));

    $provider = new GetChallengeStatusProvider($queryBus);

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['token' => 'token-2']);
  }

  #[Test]
  public function testProvideMapsOtpNotFoundHandlerFailedException(): void
  {
    $handlerFailed = new HandlerFailedException(
      new Envelope(new stdClass()),
      ['handler' => OtpNotFoundException::forIdentifier('token-3')],
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException($handlerFailed);

    $provider = new GetChallengeStatusProvider($queryBus);

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['token' => 'token-3']);
  }

  #[Test]
  public function testProvideMapsOtpNotFoundMessengerException(): void
  {
    $handlerFailed = new HandlerFailedException(
      new Envelope(new stdClass()),
      ['handler' => OtpNotFoundException::forIdentifier('token-4')],
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailed));

    $provider = new GetChallengeStatusProvider($queryBus);

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['token' => 'token-4']);
  }

  #[Test]
  public function testProvideRethrowsUnknownException(): void
  {
    $exception = new class ('boom') extends RuntimeException {};

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException($exception);

    $provider = new GetChallengeStatusProvider($queryBus);

    $this->expectException(Throwable::class);
    $provider->provide(new Get(), ['token' => 'token-5']);
  }

  #[Test]
  public function testProvideRethrowsHandlerFailedWhenOtpNotFoundMissing(): void
  {
    $handlerFailed = new HandlerFailedException(
      new Envelope(new stdClass()),
      ['handler' => new RuntimeException('boom')],
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException($handlerFailed);

    $provider = new GetChallengeStatusProvider($queryBus);

    $this->expectException(HandlerFailedException::class);
    $provider->provide(new Get(), ['token' => 'token-6']);
  }

  #[Test]
  public function testProvideMapsOtpNotFoundFromMessengerPrevious(): void
  {
    $otpNotFound = OtpNotFoundException::forIdentifier('token-7');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(MessengerRuntimeException::wrap($otpNotFound));

    $provider = new GetChallengeStatusProvider($queryBus);

    $this->expectException(NotFoundHttpException::class);
    $provider->provide(new Get(), ['token' => 'token-7']);
  }

  #[Test]
  public function testProvideMapsOtpNotFoundFromDeepMessengerPrevious(): void
  {
    $otpNotFound = OtpNotFoundException::forIdentifier('token-9');
    $middle = new RuntimeException('middle', 0, $otpNotFound);
    $outer = new RuntimeException('outer', 0, $middle);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(MessengerRuntimeException::wrap($outer));

    $provider = new GetChallengeStatusProvider($queryBus);

    $this->expectException(NotFoundHttpException::class);
    $provider->provide(new Get(), ['token' => 'token-9']);
  }

  #[Test]
  public function testProvideReturnsZeroExpiresInWhenExpired(): void
  {
    $result = new GetChallengeStatusResult(
      expiresAt: new DateTimeImmutable('-5 minutes'),
      status: 'expired',
      attemptsRemaining: 0,
      maskedRecipient: 'jo******@example.com',
      createdAt: new DateTimeImmutable('-10 minutes'),
      canResendIn: 0,
      purpose: 'login',
      channel: 'email',
      recipient: 'john.doe@example.com',
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn($result);

    $provider = new GetChallengeStatusProvider($queryBus);

    $output = $provider->provide(new Get(), ['token' => 'token-8']);

    self::assertSame(0, $output->expiresIn);
  }
  // #endregion
}
