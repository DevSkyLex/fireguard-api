<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Presentation\Api\Processor;

use ApiPlatform\Metadata\Delete;
use Auth\Infrastructure\Security\User\SecurityUser;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Webhook\Application\UseCase\Command\Subscription\DeleteWebhookSubscription\DeleteWebhookSubscriptionCommand;
use Webhook\Domain\Exception\WebhookSubscriptionNotFoundException;
use Webhook\Presentation\Api\Processor\DeleteWebhookSubscriptionProcessor;

/**
 * Test DeleteWebhookSubscriptionProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteWebhookSubscriptionProcessor::class)]
final class DeleteWebhookSubscriptionProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string WEBHOOK_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProcessDispatchesTheCommandAndReturnsNull(): void
  {
    $captured = null;
    $acknowledgement = $this->createStub(ResultMessage::class);
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (DeleteWebhookSubscriptionCommand $command) use (&$captured, $acknowledgement): ResultMessage {
        $captured = $command;

        return $acknowledgement;
      });

    $processor = new DeleteWebhookSubscriptionProcessor($commandBus, $this->authenticatedSecurity());

    $result = $processor->process(null, new Delete(), [
      'organizationId' => self::ORGANIZATION_ID,
      'webhookId' => self::WEBHOOK_ID,
    ]);

    self::assertNull($result);
    self::assertInstanceOf(DeleteWebhookSubscriptionCommand::class, $captured);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame(self::WEBHOOK_ID, $captured->subscriptionId);
    self::assertSame(self::USER_ID, $captured->actorUserId);
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new DeleteWebhookSubscriptionProcessor($this->createStub(CommandBusPort::class), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), [
      'organizationId' => self::ORGANIZATION_ID,
      'webhookId' => self::WEBHOOK_ID,
    ]);
  }

  #[Test]
  public function testProcessRequiresBothUriVariables(): void
  {
    $processor = new DeleteWebhookSubscriptionProcessor(
      $this->createStub(CommandBusPort::class),
      $this->authenticatedSecurity(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Delete(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessMapsDomainExceptionsToHttpExceptions(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      WebhookSubscriptionNotFoundException::withId(self::WEBHOOK_ID),
    );

    $processor = new DeleteWebhookSubscriptionProcessor($commandBus, $this->authenticatedSecurity());

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Delete(), [
      'organizationId' => self::ORGANIZATION_ID,
      'webhookId' => self::WEBHOOK_ID,
    ]);
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
