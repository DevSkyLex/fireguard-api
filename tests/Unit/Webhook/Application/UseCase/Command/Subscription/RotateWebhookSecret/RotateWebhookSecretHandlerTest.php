<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\UseCase\Command\Subscription\RotateWebhookSecret;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Application\Port\Outbound\{WebhookSecretCipherPort, WebhookSubscriptionRepositoryPort};
use Webhook\Application\UseCase\Command\Subscription\RotateWebhookSecret\{RotateWebhookSecretCommand, RotateWebhookSecretHandler, RotateWebhookSecretResult};
use Webhook\Domain\Exception\WebhookSubscriptionNotFoundException;
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

/**
 * Test RotateWebhookSecretHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RotateWebhookSecretHandler::class)]
final class RotateWebhookSecretHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string USER_ID = 'user-1';

  #[Test]
  public function itRotatesTheSecretAndReturnsThePlaintextOnce(): void
  {
    $repository = $this->createMock(WebhookSubscriptionRepositoryPort::class);
    $repository->method('findById')->willReturn($this->subscription());
    $repository->expects(self::once())->method('save');

    $cipher = $this->createMock(WebhookSecretCipherPort::class);
    $cipher->expects(self::once())
      ->method('encrypt')
      ->with(self::stringStartsWith('whsec_'))
      ->willReturn('NEW_CIPHER');

    $handler = new RotateWebhookSecretHandler(
      $repository,
      $cipher,
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $result = $handler->__invoke(new RotateWebhookSecretCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
    ));

    self::assertInstanceOf(RotateWebhookSecretResult::class, $result);
    self::assertSame(self::SUBSCRIPTION_ID, $result->id);
    self::assertStringStartsWith('whsec_', $result->secret);
  }

  #[Test]
  public function itThrowsWhenTheSubscriptionIsMissing(): void
  {
    $repository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $handler = new RotateWebhookSecretHandler(
      $repository,
      $this->createStub(WebhookSecretCipherPort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $this->expectException(WebhookSubscriptionNotFoundException::class);

    $handler->__invoke(new RotateWebhookSecretCommand(
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
      secretCiphertext: 'OLD_CIPHER',
      eventTypes: ['intervention.published'],
    );
  }
}
