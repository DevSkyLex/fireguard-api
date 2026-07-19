<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Presentation\Api\Processor\Totp;

use ApiPlatform\Metadata\Post;
use Otp\Application\Exception\TotpEnrollmentNotEnabledException;
use Otp\Application\UseCase\Command\Totp\DisableTotp\{DisableTotpCommand, DisableTotpResult};
use Otp\Presentation\Api\Dto\Input\Totp\DisableTotpInput;
use Otp\Presentation\Api\Dto\Output\Totp\DisableTotpOutput;
use Otp\Presentation\Api\Processor\Totp\DisableTotpProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{NotFoundHttpException, UnauthorizedHttpException, UnprocessableEntityHttpException};
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test DisableTotpProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DisableTotpProcessor::class)]
final class DisableTotpProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new DisableTotpProcessor(
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
        static fn (DisableTotpCommand $command) => 'user-1' === $command->userId && '123456' === $command->code,
      ))
      ->willReturn(DisableTotpResult::success());

    $processor = new DisableTotpProcessor(commandBus: $commandBus, security: $security);

    $output = $processor->process($this->input('123456'), new Post());

    self::assertInstanceOf(DisableTotpOutput::class, $output);
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
      ->willReturn(DisableTotpResult::failed(error: 'Invalid verification code.'));

    $processor = new DisableTotpProcessor(commandBus: $commandBus, security: $security);

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process($this->input('000000'), new Post());
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenNotEnabled(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(TotpEnrollmentNotEnabledException::forUser('user-1'));

    $processor = new DisableTotpProcessor(commandBus: $commandBus, security: $security);

    $this->expectException(NotFoundHttpException::class);

    $processor->process($this->input('123456'), new Post());
  }

  private function input(string $code): DisableTotpInput
  {
    $input = new DisableTotpInput();
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
