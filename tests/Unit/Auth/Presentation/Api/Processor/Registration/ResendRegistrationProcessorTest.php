<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor\Registration;

use ApiPlatform\Metadata\Post;
use Auth\Application\UseCase\Command\Registration\ResendRegistration\{ResendRegistrationCommand, ResendRegistrationResult};
use Auth\Presentation\Api\Dto\Input\Registration\ResendRegistrationInput;
use Auth\Presentation\Api\Dto\Output\Registration\RegisterOutput;
use Auth\Presentation\Api\Processor\Registration\ResendRegistrationProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpKernel\Exception\{NotFoundHttpException, TooManyRequestsHttpException};

#[CoversClass(ResendRegistrationProcessor::class)]
final class ResendRegistrationProcessorTest extends TestCase
{
  #[Test]
  public function testProcessReturnsOutputOnSuccess(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (ResendRegistrationCommand $command): bool => 'challenge-123' === $command->token,
      ))
      ->willReturn(ResendRegistrationResult::success(
        challengeToken: 'challenge-456',
        maskedRecipient: 'j***@example.com',
        canResendIn: 60,
      ));

    $processor = new ResendRegistrationProcessor(commandBus: $commandBus);

    $input = new ResendRegistrationInput();
    $input->token = 'challenge-123';

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(RegisterOutput::class, $output);
    self::assertTrue($output->success);
    self::assertSame('challenge-456', $output->challengeToken);
  }

  #[Test]
  public function testProcessThrowsTooManyRequestsDuringCooldown(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(ResendRegistrationResult::failed(
        message: 'Please wait before resending.',
        errorCode: ResendRegistrationResult::ERROR_RESEND_NOT_ALLOWED,
        retryAfter: 42,
      ));

    $processor = new ResendRegistrationProcessor(commandBus: $commandBus);

    $input = new ResendRegistrationInput();
    $input->token = 'challenge-123';

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process($input, new Post());
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenInvalidToken(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(ResendRegistrationResult::failed(
        message: 'Invalid or expired verification token.',
        errorCode: ResendRegistrationResult::ERROR_INVALID_TOKEN,
      ));

    $processor = new ResendRegistrationProcessor(commandBus: $commandBus);

    $input = new ResendRegistrationInput();
    $input->token = 'bogus';

    $this->expectException(NotFoundHttpException::class);

    $processor->process($input, new Post());
  }
}
