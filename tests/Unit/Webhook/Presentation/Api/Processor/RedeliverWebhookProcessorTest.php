<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Webhook\Application\UseCase\Command\Delivery\RedeliverWebhook\{RedeliverWebhookCommand, RedeliverWebhookResult};
use Webhook\Domain\Exception\WebhookDeliveryNotFoundException;
use Webhook\Presentation\Api\Processor\RedeliverWebhookProcessor;

/**
 * Test RedeliverWebhookProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RedeliverWebhookProcessor::class)]
final class RedeliverWebhookProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string WEBHOOK_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string DELIVERY_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a03';

  private const string NEW_DELIVERY_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a04';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProcessDispatchesTheCommandAndReturnsTheNewDelivery(): void
  {
    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (RedeliverWebhookCommand $command) use (&$captured): RedeliverWebhookResult {
        $captured = $command;

        return new RedeliverWebhookResult(self::NEW_DELIVERY_ID, self::WEBHOOK_ID);
      });

    $processor = new RedeliverWebhookProcessor($commandBus, $this->authenticatedSecurity());

    $output = $processor->process(null, new Post(), $this->uriVariables());

    self::assertInstanceOf(RedeliverWebhookCommand::class, $captured);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame(self::USER_ID, $captured->actorUserId);
    self::assertSame(self::WEBHOOK_ID, $captured->subscriptionId);
    self::assertSame(self::DELIVERY_ID, $captured->deliveryId);

    self::assertSame(self::NEW_DELIVERY_ID, $output->deliveryId);
    self::assertSame(self::WEBHOOK_ID, $output->subscriptionId);
    self::assertSame('queued', $output->status);
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new RedeliverWebhookProcessor($this->createStub(CommandBusPort::class), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), $this->uriVariables());
  }

  #[Test]
  public function testProcessRequiresTheDeliveryIdUriVariable(): void
  {
    $processor = new RedeliverWebhookProcessor(
      $this->createStub(CommandBusPort::class),
      $this->authenticatedSecurity(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Post(), [
      'organizationId' => self::ORGANIZATION_ID,
      'webhookId' => self::WEBHOOK_ID,
    ]);
  }

  #[Test]
  public function testProcessMapsAMissingDeliveryToNotFound(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      WebhookDeliveryNotFoundException::withId(self::DELIVERY_ID),
    );

    $processor = new RedeliverWebhookProcessor($commandBus, $this->authenticatedSecurity());

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Post(), $this->uriVariables());
  }

  /**
   * Method uriVariables.
   *
   * @return array<string, string> the complete URI variable set
   */
  private function uriVariables(): array
  {
    return [
      'organizationId' => self::ORGANIZATION_ID,
      'webhookId' => self::WEBHOOK_ID,
      'deliveryId' => self::DELIVERY_ID,
    ];
  }

  /**
   * Method authenticatedSecurity.
   *
   * @return Security a security stub returning an authenticated user
   */
  private function authenticatedSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return $security;
  }
}
