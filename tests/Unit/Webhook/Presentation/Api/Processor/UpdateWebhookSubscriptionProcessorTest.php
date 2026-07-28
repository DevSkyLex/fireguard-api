<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Presentation\Api\Processor;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Webhook\Application\UseCase\Command\Subscription\UpdateWebhookSubscription\{UpdateWebhookSubscriptionCommand, UpdateWebhookSubscriptionResult};
use Webhook\Domain\Exception\WebhookSubscriptionNotFoundException;
use Webhook\Presentation\Api\Dto\Input\UpdateWebhookSubscriptionInput;
use Webhook\Presentation\Api\Factory\WebhookSubscriptionOutputFactory;
use Webhook\Presentation\Api\Processor\UpdateWebhookSubscriptionProcessor;

/**
 * Test UpdateWebhookSubscriptionProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateWebhookSubscriptionProcessor::class)]
final class UpdateWebhookSubscriptionProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string WEBHOOK_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProcessForwardsThePartialUpdateAndReturnsTheRefreshedView(): void
  {
    $input = new UpdateWebhookSubscriptionInput();
    $input->url = 'https://example.com/updated';
    $input->eventTypes = ['intervention.closed'];
    $input->isActive = false;
    $input->description = 'Paused';

    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (UpdateWebhookSubscriptionCommand $command) use (&$captured): UpdateWebhookSubscriptionResult {
        $captured = $command;

        return new UpdateWebhookSubscriptionResult(
          id: self::WEBHOOK_ID,
          organizationId: self::ORGANIZATION_ID,
          url: 'https://example.com/updated',
          eventTypes: ['intervention.closed'],
          isActive: false,
          description: 'Paused',
          createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
          updatedAt: new DateTimeImmutable('2026-07-18T01:00:00+00:00'),
        );
      });

    $processor = new UpdateWebhookSubscriptionProcessor(
      $commandBus,
      $this->authenticatedSecurity(),
      new WebhookSubscriptionOutputFactory(),
    );

    $output = $processor->process($input, new Patch(), [
      'organizationId' => self::ORGANIZATION_ID,
      'webhookId' => self::WEBHOOK_ID,
    ]);

    self::assertInstanceOf(UpdateWebhookSubscriptionCommand::class, $captured);
    self::assertSame(self::USER_ID, $captured->actorUserId);
    self::assertSame(self::WEBHOOK_ID, $captured->subscriptionId);
    self::assertSame('https://example.com/updated', $captured->url);
    self::assertSame(['intervention.closed'], $captured->eventTypes);
    self::assertFalse($captured->isActive);
    self::assertSame('Paused', $captured->description);

    self::assertSame(self::WEBHOOK_ID, $output->id);
    self::assertSame('https://example.com/updated', $output->url);
    self::assertFalse($output->isActive);
    self::assertSame('2026-07-18T00:00:00+00:00', $output->createdAt);
  }

  #[Test]
  public function testProcessPassesOmittedFieldsThroughAsNull(): void
  {
    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (UpdateWebhookSubscriptionCommand $command) use (&$captured): UpdateWebhookSubscriptionResult {
        $captured = $command;

        return new UpdateWebhookSubscriptionResult(
          id: self::WEBHOOK_ID,
          organizationId: self::ORGANIZATION_ID,
          url: 'https://example.com/hook',
          eventTypes: [],
          isActive: true,
          description: '',
          createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
          updatedAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
        );
      });

    $processor = new UpdateWebhookSubscriptionProcessor(
      $commandBus,
      $this->authenticatedSecurity(),
      new WebhookSubscriptionOutputFactory(),
    );

    $processor->process(new UpdateWebhookSubscriptionInput(), new Patch(), [
      'organizationId' => self::ORGANIZATION_ID,
      'webhookId' => self::WEBHOOK_ID,
    ]);

    self::assertInstanceOf(UpdateWebhookSubscriptionCommand::class, $captured);
    self::assertNull($captured->url);
    self::assertNull($captured->eventTypes);
    self::assertNull($captured->isActive);
    self::assertNull($captured->description);
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new UpdateWebhookSubscriptionProcessor(
      $this->createStub(CommandBusPort::class),
      $security,
      new WebhookSubscriptionOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new UpdateWebhookSubscriptionInput(), new Patch(), [
      'organizationId' => self::ORGANIZATION_ID,
      'webhookId' => self::WEBHOOK_ID,
    ]);
  }

  #[Test]
  public function testProcessRequiresBothUriVariables(): void
  {
    $processor = new UpdateWebhookSubscriptionProcessor(
      $this->createStub(CommandBusPort::class),
      $this->authenticatedSecurity(),
      new WebhookSubscriptionOutputFactory(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new UpdateWebhookSubscriptionInput(), new Patch(), []);
  }

  #[Test]
  public function testProcessMapsAMissingSubscriptionToNotFound(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      WebhookSubscriptionNotFoundException::withId(self::WEBHOOK_ID),
    );

    $processor = new UpdateWebhookSubscriptionProcessor(
      $commandBus,
      $this->authenticatedSecurity(),
      new WebhookSubscriptionOutputFactory(),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(new UpdateWebhookSubscriptionInput(), new Patch(), [
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
