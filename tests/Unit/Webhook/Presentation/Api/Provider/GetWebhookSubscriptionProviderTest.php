<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Webhook\Application\UseCase\Query\Subscription\GetWebhookSubscription\{GetWebhookSubscriptionQuery, GetWebhookSubscriptionResult};
use Webhook\Presentation\Api\Dto\Output\WebhookSubscriptionOutput;
use Webhook\Presentation\Api\Factory\WebhookSubscriptionOutputFactory;
use Webhook\Presentation\Api\Provider\GetWebhookSubscriptionProvider;

/**
 * Test GetWebhookSubscriptionProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetWebhookSubscriptionProvider::class)]
final class GetWebhookSubscriptionProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string WEBHOOK_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProvideAsksTheQueryAndReturnsTheSubscriptionOutput(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $captured = null;
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturnCallback(function (GetWebhookSubscriptionQuery $query) use (&$captured): GetWebhookSubscriptionResult {
        $captured = $query;

        return new GetWebhookSubscriptionResult(
          id: self::WEBHOOK_ID,
          organizationId: self::ORGANIZATION_ID,
          url: 'https://example.com/hook',
          eventTypes: ['intervention.published'],
          isActive: true,
          description: '',
          createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
          updatedAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
        );
      });

    $provider = new GetWebhookSubscriptionProvider($queryBus, $security, new WebhookSubscriptionOutputFactory());

    $output = $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID, 'webhookId' => self::WEBHOOK_ID]);

    self::assertInstanceOf(GetWebhookSubscriptionQuery::class, $captured);
    self::assertSame(self::USER_ID, $captured->userId);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame(self::WEBHOOK_ID, $captured->subscriptionId);

    self::assertInstanceOf(WebhookSubscriptionOutput::class, $output);
    self::assertSame(self::WEBHOOK_ID, $output->id);
    self::assertSame('https://example.com/hook', $output->url);
  }

  #[Test]
  public function testProvideRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetWebhookSubscriptionProvider(
      $this->createStub(QueryBusPort::class),
      $security,
      new WebhookSubscriptionOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID, 'webhookId' => self::WEBHOOK_ID]);
  }

  #[Test]
  public function testProvideRequiresUriVariables(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $provider = new GetWebhookSubscriptionProvider(
      $this->createStub(QueryBusPort::class),
      $security,
      new WebhookSubscriptionOutputFactory(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new Get(), []);
  }
}
