<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Presentation\Api\Processor\Totp;

use ApiPlatform\Metadata\Post;
use Otp\Application\Exception\TotpPendingEnrollmentNotFoundException;
use Otp\Application\UseCase\Command\Totp\ConfirmTotp\{ConfirmTotpCommand, ConfirmTotpResult};
use Otp\Presentation\Api\Dto\Input\Totp\ConfirmTotpInput;
use Otp\Presentation\Api\Dto\Output\Totp\ConfirmTotpOutput;
use Otp\Presentation\Api\Processor\Totp\ConfirmTotpProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{NotFoundHttpException, UnauthorizedHttpException, UnprocessableEntityHttpException};
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test ConfirmTotpProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConfirmTotpProcessor::class)]
final class ConfirmTotpProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new ConfirmTotpProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
    );

    $this->expectException(UnauthorizedHttpException::class);

    $processor->process($this->input('123456'), new Post());
  }

  #[Test]
  public function testProcessMapsSuccessfulResult(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->user());

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (ConfirmTotpCommand $command) => 'user-1' === $command->userId && '123456' === $command->code,
      ))
      ->willReturn(ConfirmTotpResult::success());

    $processor = new ConfirmTotpProcessor(commandBus: $commandBus, security: $security);

    $output = $processor->process($this->input('123456'), new Post());

    self::assertInstanceOf(ConfirmTotpOutput::class, $output);
    self::assertTrue($output->success);
  }

  #[Test]
  public function testProcessThrowsUnprocessableEntityOnInvalidCode(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(ConfirmTotpResult::failed(attemptsRemaining: 4, error: 'Invalid verification code.'));

    $processor = new ConfirmTotpProcessor(commandBus: $commandBus, security: $security);

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process($this->input('000000'), new Post());
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenNoPendingEnrollment(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(TotpPendingEnrollmentNotFoundException::forUser('user-1'));

    $processor = new ConfirmTotpProcessor(commandBus: $commandBus, security: $security);

    $this->expectException(NotFoundHttpException::class);

    $processor->process($this->input('123456'), new Post());
  }

  private function input(string $code): ConfirmTotpInput
  {
    $input = new ConfirmTotpInput();
    $input->code = $code;

    return $input;
  }

  private function user(): UserInterface
  {
    return new class () implements UserInterface {
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
  }
}
