<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Presentation\Api\Processor\Challenge;

use ApiPlatform\Metadata\Post;
use Otp\Application\Exception\OtpNotFoundException;
use Otp\Application\UseCase\Command\Challenge\VerifyOtp\{VerifyOtpCommand, VerifyOtpResult};
use Otp\Presentation\Api\Dto\Input\Challenge\VerifyOtpInput;
use Otp\Presentation\Api\Dto\Output\Challenge\VerifyOtpOutput;
use Otp\Presentation\Api\Processor\Challenge\VerifyOtpProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Infrastructure\Exception\MessengerRuntimeException;
use stdClass;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Test VerifyOtpProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(VerifyOtpProcessor::class)]
final class VerifyOtpProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenIdentifiersMissing(): void
  {
    $processor = new VerifyOtpProcessor($this->createMock(CommandBusPort::class));

    $input = new VerifyOtpInput();
    $input->code = '123456';

    $this->expectException(NotFoundHttpException::class);

    $processor->process($input, new Post());
  }

  #[Test]
  public function testProcessMapsResult(): void
  {
    $input = new VerifyOtpInput();
    $input->code = '123456';

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with($this->callback(
        fn (VerifyOtpCommand $command) => 'token-1' === $command->challengeToken
          && '123456' === $command->code,
      ))
      ->willReturn(new VerifyOtpResult(
        success: true,
        attemptsRemaining: 0,
        error: null,
      ));

    $processor = new VerifyOtpProcessor($commandBus);

    $output = $processor->process($input, new Post(), ['token' => 'token-1']);

    self::assertInstanceOf(VerifyOtpOutput::class, $output);
    self::assertTrue($output->success);
    self::assertSame(0, $output->attemptsRemaining);
  }

  #[Test]
  public function testProcessMapsOtpNotFoundException(): void
  {
    $input = new VerifyOtpInput();
    $input->code = '123456';

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(OtpNotFoundException::forIdentifier('token-2'));

    $processor = new VerifyOtpProcessor($commandBus);

    $this->expectException(NotFoundHttpException::class);

    $processor->process($input, new Post(), ['token' => 'token-2']);
  }

  #[Test]
  public function testProcessMapsHandlerFailedException(): void
  {
    $input = new VerifyOtpInput();
    $input->code = '123456';

    $exception = new HandlerFailedException(
      new Envelope(new stdClass()),
      [OtpNotFoundException::forIdentifier('otp-1')],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException($exception);

    $processor = new VerifyOtpProcessor($commandBus);

    $this->expectException(NotFoundHttpException::class);

    $processor->process($input, new Post(), ['id' => 'otp-1']);
  }

  #[Test]
  public function testProcessMapsMessengerRuntimeException(): void
  {
    $input = new VerifyOtpInput();
    $input->code = '123456';

    $handlerFailed = new HandlerFailedException(
      new Envelope(new stdClass()),
      [OtpNotFoundException::forIdentifier('otp-2')],
    );

    $exception = MessengerRuntimeException::wrap($handlerFailed);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException($exception);

    $processor = new VerifyOtpProcessor($commandBus);

    $this->expectException(NotFoundHttpException::class);

    $processor->process($input, new Post(), ['id' => 'otp-2']);
  }

  #[Test]
  public function testProcessRethrowsMessengerRuntimeExceptionWhenNotFoundMissing(): void
  {
    $input = new VerifyOtpInput();
    $input->code = '123456';

    $handlerFailed = new HandlerFailedException(
      new Envelope(new stdClass()),
      [new RuntimeException('boom')],
    );

    $exception = MessengerRuntimeException::wrap($handlerFailed);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException($exception);

    $processor = new VerifyOtpProcessor($commandBus);

    $this->expectException(MessengerRuntimeException::class);

    $processor->process($input, new Post(), ['id' => 'otp-3']);
  }
  // #endregion
}
