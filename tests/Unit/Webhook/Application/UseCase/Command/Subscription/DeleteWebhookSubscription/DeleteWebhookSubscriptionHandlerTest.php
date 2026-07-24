<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\UseCase\Command\Subscription\DeleteWebhookSubscription;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Webhook\Application\Port\Outbound\WebhookSubscriptionRepositoryPort;
use Webhook\Application\UseCase\Command\Subscription\DeleteWebhookSubscription\{DeleteWebhookSubscriptionCommand, DeleteWebhookSubscriptionHandler, DeleteWebhookSubscriptionResult};
use Webhook\Domain\Event\Subscription\WebhookSubscriptionDeletedEvent;
use Webhook\Domain\Exception\WebhookSubscriptionNotFoundException;
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

/**
 * Test DeleteWebhookSubscriptionHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteWebhookSubscriptionHandler::class)]
final class DeleteWebhookSubscriptionHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string USER_ID = 'user-1';

  #[Test]
  public function itRemovesTheSubscriptionAndDispatchesTheDeletedEvent(): void
  {
    $repository = $this->createMock(WebhookSubscriptionRepositoryPort::class);
    $repository->method('findById')->willReturn($this->subscription());
    $repository->expects(self::once())->method('remove')->with(self::isInstanceOf(WebhookSubscription::class));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (WebhookSubscriptionDeletedEvent $event): bool {
        self::assertSame(self::ORGANIZATION_ID, $event->organizationId);
        self::assertSame(self::SUBSCRIPTION_ID, $event->subscriptionId);
        self::assertSame(self::USER_ID, $event->actorUserId);

        return true;
      }));

    $handler = new DeleteWebhookSubscriptionHandler(
      $repository,
      $this->createStub(OrganizationAuthorizationPort::class),
      $eventDispatcher,
    );

    $result = $handler->__invoke(new DeleteWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
    ));

    self::assertInstanceOf(DeleteWebhookSubscriptionResult::class, $result);
    self::assertSame(self::SUBSCRIPTION_ID, $result->subscriptionId);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
  }

  #[Test]
  public function itThrowsWhenTheSubscriptionIsMissing(): void
  {
    $repository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $handler = new DeleteWebhookSubscriptionHandler(
      $repository,
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(WebhookSubscriptionNotFoundException::class);

    $handler->__invoke(new DeleteWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
    ));
  }

  #[Test]
  public function itThrowsWhenTheSubscriptionBelongsToAnotherOrganization(): void
  {
    $repository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $repository->method('findById')->willReturn($this->subscription('018f0b68-6758-7a12-8a1d-3f0d97f64aaa'));

    $handler = new DeleteWebhookSubscriptionHandler(
      $repository,
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(WebhookSubscriptionNotFoundException::class);

    $handler->__invoke(new DeleteWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
    ));
  }

  /**
   * Method subscription.
   *
   * @param string $organizationId the owning organization identifier
   *
   * @return WebhookSubscription a subscription aggregate under test
   */
  private function subscription(string $organizationId = self::ORGANIZATION_ID): WebhookSubscription
  {
    return WebhookSubscription::create(
      id: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: $organizationId,
      url: 'https://example.com/hook',
      secretCiphertext: 'CIPHER',
      eventTypes: ['intervention.published'],
    );
  }
}
