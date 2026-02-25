<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Presentation\Api\Processor\Challenge;

use ApiPlatform\Metadata\Post;
use DateTimeImmutable;
use Otp\Application\Exception\{OtpNotFoundException, ResendNotAllowedException};
use Otp\Application\UseCase\Command\Challenge\ResendChallenge\{ResendChallengeCommand, ResendChallengeResult};
use Otp\Presentation\Api\Dto\Output\Challenge\ChallengeOutput;
use Otp\Presentation\Api\Processor\Challenge\ResendChallengeProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{NotFoundHttpException, TooManyRequestsHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test ResendChallengeProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ResendChallengeProcessor::class)]
final class ResendChallengeProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenTokenMissing(): void
  {
    $security = $this->createMock(Security::class);

    $processor = new ResendChallengeProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Post());
  }

  #[Test]
  public function testProcessThrowsWhenUserMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new ResendChallengeProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Post(), ['token' => 'token-1']);
  }

  #[Test]
  public function testProcessReturnsChallengeOutput(): void
  {
    $user = new class () implements UserInterface {
      public function getUserIdentifier(): string
      {
        return 'user-1';
      }

      public function getRoles(): array
      {
        return [];
      }

      public function eraseCredentials(): void
      {
      }
    };

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with($this->callback(
        fn (ResendChallengeCommand $command) => 'token-1' === $command->challengeToken
          && 'user-1' === $command->userId,
      ))
      ->willReturn(new ResendChallengeResult(
        token: 'token-2',
        purpose: 'login',
        channel: 'email',
        maskedRecipient: 'jo******@example.com',
        expiresAt: new DateTimeImmutable('+5 minutes'),
        maxAttempts: 5,
        canResendIn: 30,
      ));

    $processor = new ResendChallengeProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $output = $processor->process(null, new Post(), ['token' => 'token-1']);

    self::assertInstanceOf(ChallengeOutput::class, $output);
    self::assertSame('token-2', $output->token);
    self::assertSame('login', $output->purpose);
    self::assertSame(30, $output->canResendIn);
  }

  #[Test]
  public function testProcessMapsOtpNotFoundException(): void
  {
    $user = new class () implements UserInterface {
      public function getUserIdentifier(): string
      {
        return 'user-2';
      }

      public function getRoles(): array
      {
        return [];
      }

      public function eraseCredentials(): void
      {
      }
    };

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(OtpNotFoundException::forIdentifier('token-3'));

    $processor = new ResendChallengeProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Post(), ['token' => 'token-3']);
  }

  #[Test]
  public function testProcessMapsResendNotAllowedException(): void
  {
    $user = new class () implements UserInterface {
      public function getUserIdentifier(): string
      {
        return 'user-3';
      }

      public function getRoles(): array
      {
        return [];
      }

      public function eraseCredentials(): void
      {
      }
    };

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new ResendNotAllowedException(15));

    $processor = new ResendChallengeProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process(null, new Post(), ['token' => 'token-4']);
  }

  #[Test]
  public function testProcessMapsOtpNotFoundHandlerFailedException(): void
  {
    $user = new class () implements UserInterface {
      public function getUserIdentifier(): string
      {
        return 'user-4';
      }

      public function getRoles(): array
      {
        return [];
      }

      public function eraseCredentials(): void
      {
      }
    };

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $handlerFailed = new HandlerFailedException(
      new Envelope(new stdClass()),
      ['handler' => OtpNotFoundException::forIdentifier('token-5')],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException($handlerFailed);

    $processor = new ResendChallengeProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Post(), ['token' => 'token-5']);
  }

  #[Test]
  public function testProcessMapsResendNotAllowedMessengerException(): void
  {
    $user = new class () implements UserInterface {
      public function getUserIdentifier(): string
      {
        return 'user-5';
      }

      public function getRoles(): array
      {
        return [];
      }

      public function eraseCredentials(): void
      {
      }
    };

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $handlerFailed = new HandlerFailedException(
      new Envelope(new stdClass()),
      ['handler' => new ResendNotAllowedException(20)],
    );

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailed));

    $processor = new ResendChallengeProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process(null, new Post(), ['token' => 'token-6']);
  }

  #[Test]
  public function testProcessMapsResendNotAllowedHandlerFailedException(): void
  {
    $user = new class () implements UserInterface {
      public function getUserIdentifier(): string
      {
        return 'user-6';
      }

      public function getRoles(): array
      {
        return [];
      }

      public function eraseCredentials(): void
      {
      }
    };

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $handlerFailed = new HandlerFailedException(
      new Envelope(new stdClass()),
      ['handler' => new ResendNotAllowedException(25)],
    );

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException($handlerFailed);

    $processor = new ResendChallengeProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process(null, new Post(), ['token' => 'token-7']);
  }

  #[Test]
  public function testProcessMapsOtpNotFoundMessengerPreviousChain(): void
  {
    $user = new class () implements UserInterface {
      public function getUserIdentifier(): string
      {
        return 'user-7';
      }

      public function getRoles(): array
      {
        return [];
      }

      public function eraseCredentials(): void
      {
      }
    };

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $inner = OtpNotFoundException::forIdentifier('token-8');
    $outer = new RuntimeException('wrapper', 0, $inner);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($outer));

    $processor = new ResendChallengeProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Post(), ['token' => 'token-8']);
  }

  #[Test]
  public function testProcessMapsResendNotAllowedMessengerPreviousChain(): void
  {
    $user = new class () implements UserInterface {
      public function getUserIdentifier(): string
      {
        return 'user-8';
      }

      public function getRoles(): array
      {
        return [];
      }

      public function eraseCredentials(): void
      {
      }
    };

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $inner = new ResendNotAllowedException(30);
    $outer = new RuntimeException('wrapper', 0, $inner);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($outer));

    $processor = new ResendChallengeProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process(null, new Post(), ['token' => 'token-9']);
  }

  #[Test]
  public function testProcessRethrowsUnhandledException(): void
  {
    $user = new class () implements UserInterface {
      public function getUserIdentifier(): string
      {
        return 'user-9';
      }

      public function getRoles(): array
      {
        return [];
      }

      public function eraseCredentials(): void
      {
      }
    };

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException('boom'));

    $processor = new ResendChallengeProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(RuntimeException::class);

    $processor->process(null, new Post(), ['token' => 'token-10']);
  }
  // #endregion
}
