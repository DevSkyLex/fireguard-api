<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\UseCase\Command\Subscription\UpdateWebhookSubscription;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Application\Port\Outbound\WebhookSubscriptionRepositoryPort;
use Webhook\Application\UseCase\Command\Subscription\UpdateWebhookSubscription\{UpdateWebhookSubscriptionCommand, UpdateWebhookSubscriptionHandler, UpdateWebhookSubscriptionResult};
use Webhook\Domain\Exception\{WebhookSubscriptionNotFoundException, WebhookValidationException};
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\Service\WebhookUrlPolicy;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

/**
 * Test UpdateWebhookSubscriptionHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateWebhookSubscriptionHandler::class)]
final class UpdateWebhookSubscriptionHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string USER_ID = 'user-1';

  #[Test]
  public function itUpdatesTheChangedFieldsAndSaves(): void
  {
    $repository = $this->createMock(WebhookSubscriptionRepositoryPort::class);
    $repository->method('findById')->willReturn($this->subscription());
    $repository->expects(self::once())->method('save');

    $handler = new UpdateWebhookSubscriptionHandler(
      $repository,
      new WebhookUrlPolicy(allowInsecureUrls: false),
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $result = $handler->__invoke(new UpdateWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
      url: 'https://new.example.com/hook',
      eventTypes: ['inspection.closed'],
      isActive: false,
      description: 'Updated',
    ));

    self::assertInstanceOf(UpdateWebhookSubscriptionResult::class, $result);
    self::assertSame('https://new.example.com/hook', $result->url);
    self::assertSame(['inspection.closed'], $result->eventTypes);
    self::assertFalse($result->isActive);
    self::assertSame('Updated', $result->description);
  }

  #[Test]
  public function itLeavesFieldsUnchangedWhenTheCommandOmitsThem(): void
  {
    $repository = $this->createMock(WebhookSubscriptionRepositoryPort::class);
    $repository->method('findById')->willReturn($this->subscription());
    $repository->expects(self::once())->method('save');

    $handler = new UpdateWebhookSubscriptionHandler(
      $repository,
      new WebhookUrlPolicy(allowInsecureUrls: false),
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $result = $handler->__invoke(new UpdateWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
    ));

    self::assertSame('https://example.com/hook', $result->url);
    self::assertSame(['intervention.published'], $result->eventTypes);
    self::assertTrue($result->isActive);
  }

  #[Test]
  public function itThrowsWhenAnUpdatedEventTypeIsNotAllowed(): void
  {
    $repository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $repository->method('findById')->willReturn($this->subscription());

    $handler = new UpdateWebhookSubscriptionHandler(
      $repository,
      new WebhookUrlPolicy(allowInsecureUrls: false),
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $this->expectException(WebhookValidationException::class);

    $handler->__invoke(new UpdateWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
      eventTypes: ['webhook.ping'],
    ));
  }

  #[Test]
  public function itThrowsWhenTheUpdatedEventTypesAreEmpty(): void
  {
    $repository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $repository->method('findById')->willReturn($this->subscription());

    $handler = new UpdateWebhookSubscriptionHandler(
      $repository,
      new WebhookUrlPolicy(allowInsecureUrls: false),
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $this->expectException(WebhookValidationException::class);

    $handler->__invoke(new UpdateWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
      eventTypes: [],
    ));
  }

  #[Test]
  public function itThrowsWhenTheSubscriptionIsMissing(): void
  {
    $repository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $handler = new UpdateWebhookSubscriptionHandler(
      $repository,
      new WebhookUrlPolicy(allowInsecureUrls: false),
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $this->expectException(WebhookSubscriptionNotFoundException::class);

    $handler->__invoke(new UpdateWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
    ));
  }

  /**
   * Method subscription.
   *
   * @return WebhookSubscription a subscription aggregate under test
   */
  private function subscription(): WebhookSubscription
  {
    return WebhookSubscription::create(
      id: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: self::ORGANIZATION_ID,
      url: 'https://example.com/hook',
      secretCiphertext: 'CIPHER',
      eventTypes: ['intervention.published'],
    );
  }
}
