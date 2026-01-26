<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Presentation\Api\Processor\Totp;

use ApiPlatform\Metadata\Post;
use Otp\Application\UseCase\Command\Totp\SetupTotp\{SetupTotpCommand, SetupTotpResult};
use Otp\Presentation\Api\Dto\Output\Totp\SetupTotpOutput;
use Otp\Presentation\Api\Processor\Totp\SetupTotpProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test SetupTotpProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SetupTotpProcessor::class)]
final class SetupTotpProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new SetupTotpProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Post());
  }

  #[Test]
  public function testProcessMapsResult(): void
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

      public function getEmail(): string
      {
        return 'user@example.com';
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
        fn (SetupTotpCommand $command) => 'user-1' === $command->userId
          && 'user@example.com' === $command->accountName,
      ))
      ->willReturn(new SetupTotpResult(
        secret: 'SECRET',
        qrCodeUri: 'otpauth://totp/test',
      ));

    $processor = new SetupTotpProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $output = $processor->process(null, new Post());

    self::assertInstanceOf(SetupTotpOutput::class, $output);
    self::assertSame('SECRET', $output->secret);
    self::assertSame('otpauth://totp/test', $output->qrCodeUri);
  }
  // #endregion
}
