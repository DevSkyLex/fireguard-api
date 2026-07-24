<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\UseCase\Query\Subscription\GetWebhookSubscription;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Application\Port\Outbound\WebhookSubscriptionRepositoryPort;
use Webhook\Application\UseCase\Query\Subscription\GetWebhookSubscription\{GetWebhookSubscriptionHandler, GetWebhookSubscriptionQuery, GetWebhookSubscriptionResult};
use Webhook\Domain\Exception\WebhookSubscriptionNotFoundException;
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

/**
 * Test GetWebhookSubscriptionHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetWebhookSubscriptionHandler::class)]
final class GetWebhookSubscriptionHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string USER_ID = 'user-1';

  #[Test]
  public function itReturnsTheSubscriptionView(): void
  {
    $repository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $repository->method('findById')->willReturn($this->subscription());

    $handler = new GetWebhookSubscriptionHandler(
      $repository,
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $result = $handler->__invoke(new GetWebhookSubscriptionQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
    ));

    self::assertInstanceOf(GetWebhookSubscriptionResult::class, $result);
    self::assertSame(self::SUBSCRIPTION_ID, $result->id);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame('https://example.com/hook', $result->url);
    self::assertSame(['intervention.published'], $result->eventTypes);
    self::assertTrue($result->isActive);
  }

  #[Test]
  public function itThrowsWhenTheSubscriptionIsMissing(): void
  {
    $repository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $handler = new GetWebhookSubscriptionHandler(
      $repository,
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $this->expectException(WebhookSubscriptionNotFoundException::class);

    $handler->__invoke(new GetWebhookSubscriptionQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
    ));
  }

  #[Test]
  public function itThrowsWhenTheSubscriptionBelongsToAnotherOrganization(): void
  {
    $repository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $repository->method('findById')->willReturn($this->subscription('018f0b68-6758-7a12-8a1d-3f0d97f64aaa'));

    $handler = new GetWebhookSubscriptionHandler(
      $repository,
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $this->expectException(WebhookSubscriptionNotFoundException::class);

    $handler->__invoke(new GetWebhookSubscriptionQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
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
