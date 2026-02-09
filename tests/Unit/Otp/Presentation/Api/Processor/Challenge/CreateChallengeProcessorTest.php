<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Presentation\Api\Processor\Challenge;

use ApiPlatform\Metadata\Post;
use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{OtpChannel, OtpPurpose};
use Otp\Application\UseCase\Command\Challenge\GenerateOtp\{GenerateOtpCommand, GenerateOtpResult};
use Otp\Presentation\Api\Dto\Input\Challenge\CreateChallengeInput;
use Otp\Presentation\Api\Dto\Output\Challenge\ChallengeOutput;
use Otp\Presentation\Api\Processor\Challenge\CreateChallengeProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test CreateChallengeProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateChallengeProcessor::class)]
final class CreateChallengeProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new CreateChallengeProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new CreateChallengeInput(), new Post());
  }

  #[Test]
  public function testProcessThrowsWhenRecipientMissing(): void
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

    $processor = new CreateChallengeProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      security: $security,
    );

    $input = new CreateChallengeInput();
    $input->purpose = 'login';
    $input->channel = 'sms';
    $input->recipient = null;

    $this->expectException(BadRequestHttpException::class);

    $processor->process($input, new Post());
  }

  #[Test]
  public function testProcessCreatesChallenge(): void
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

      public function getEmail(): string
      {
        return 'john.doe@example.com';
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
        fn (GenerateOtpCommand $command) => 'user-2' === $command->userId
          && OtpPurpose::LOGIN === $command->purpose
          && OtpChannel::EMAIL === $command->channel
          && 'john.doe@example.com' === $command->recipient,
      ))
      ->willReturn(new GenerateOtpResult(
        otpId: 'otp-1',
        token: 'token-1',
        maskedRecipient: 'jo******@example.com',
        expiresAt: new DateTimeImmutable('+5 minutes'),
        maxAttempts: 5,
      ));

    $processor = new CreateChallengeProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $input = new CreateChallengeInput();
    $input->purpose = 'login';
    $input->channel = 'email';

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(ChallengeOutput::class, $output);
    self::assertSame('token-1', $output->token);
    self::assertSame('login', $output->purpose);
    self::assertSame('email', $output->channel);
    self::assertSame('jo******@example.com', $output->maskedRecipient);
    self::assertSame(5, $output->attemptsRemaining);
  }

  #[Test]
  public function testProcessUsesPhoneWhenSmsRecipientMissing(): void
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

      public function getPhone(): string
      {
        return '+33612345678';
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
        fn (GenerateOtpCommand $command) => 'user-3' === $command->userId
          && OtpPurpose::LOGIN === $command->purpose
          && OtpChannel::SMS === $command->channel
          && '+33612345678' === $command->recipient,
      ))
      ->willReturn(new GenerateOtpResult(
        otpId: 'otp-2',
        token: 'token-2',
        maskedRecipient: '****5678',
        expiresAt: new DateTimeImmutable('+5 minutes'),
        maxAttempts: 5,
      ));

    $processor = new CreateChallengeProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $input = new CreateChallengeInput();
    $input->purpose = 'login';
    $input->channel = 'sms';

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(ChallengeOutput::class, $output);
    self::assertSame('token-2', $output->token);
    self::assertSame('sms', $output->channel);
    self::assertSame('****5678', $output->maskedRecipient);
  }
  // #endregion
}
