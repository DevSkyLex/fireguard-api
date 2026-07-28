<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Presentation\Controller;

use Billing\Application\UseCase\Command\HandleStripeWebhook\HandleStripeWebhookCommand;
use Billing\Domain\Exception\InvalidWebhookSignatureException;
use Billing\Presentation\Controller\StripeWebhookController;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpFoundation\{Request, Response};

/**
 * Test StripeWebhookControllerTest.
 *
 * @category Controller Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(StripeWebhookController::class)]
final class StripeWebhookControllerTest extends TestCase
{
  #[Test]
  public function testItForwardsThePayloadAndSignatureAndAnswersNoContent(): void
  {
    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (HandleStripeWebhookCommand $command) use (&$captured): ResultMessage {
        $captured = $command;

        return $this->createStub(ResultMessage::class);
      });

    $response = (new StripeWebhookController($commandBus))($this->request('{"id":"evt_1"}', 't=1,v1=abc'));

    self::assertInstanceOf(HandleStripeWebhookCommand::class, $captured);
    self::assertSame('{"id":"evt_1"}', $captured->payload);
    self::assertSame('t=1,v1=abc', $captured->signature);

    self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
  }

  #[Test]
  public function testItAnswersBadRequestOnAnInvalidSignature(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(InvalidWebhookSignatureException::create()),
    );

    $response = (new StripeWebhookController($commandBus))($this->request('{}', 'bad'));

    self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    self::assertSame('Invalid signature.', $response->getContent());
  }

  #[Test]
  public function testItTreatsAMissingSignatureHeaderAsAnEmptyString(): void
  {
    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (HandleStripeWebhookCommand $command) use (&$captured): ResultMessage {
        $captured = $command;

        return $this->createStub(ResultMessage::class);
      });

    (new StripeWebhookController($commandBus))(new Request(content: '{}'));

    self::assertInstanceOf(HandleStripeWebhookCommand::class, $captured);
    self::assertSame('', $captured->signature);
  }

  #[Test]
  public function testItRethrowsUnrelatedMessengerFailuresSoStripeRetries(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new RuntimeException('Database down.')),
    );

    $this->expectException(MessengerRuntimeException::class);

    (new StripeWebhookController($commandBus))($this->request('{}', 't=1,v1=abc'));
  }

  /**
   * Method request.
   *
   * @param string $payload the raw request body
   * @param string $signature the Stripe-Signature header value
   *
   * @return Request the incoming request
   */
  private function request(string $payload, string $signature): Request
  {
    $request = new Request(content: $payload);
    $request->headers->set('Stripe-Signature', $signature);

    return $request;
  }
}
