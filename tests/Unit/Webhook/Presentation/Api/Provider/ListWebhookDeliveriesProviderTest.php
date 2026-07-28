<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Presentation\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Webhook\Application\UseCase\Query\Delivery\ListWebhookDeliveries\{ListWebhookDeliveriesQuery, ListWebhookDeliveriesResult, WebhookDeliveryResult};
use Webhook\Domain\Exception\WebhookSubscriptionNotFoundException;
use Webhook\Presentation\Api\Dto\Output\WebhookDeliveryOutput;
use Webhook\Presentation\Api\Factory\WebhookDeliveryOutputFactory;
use Webhook\Presentation\Api\Provider\ListWebhookDeliveriesProvider;

use function iterator_to_array;

/**
 * Test ListWebhookDeliveriesProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListWebhookDeliveriesProvider::class)]
final class ListWebhookDeliveriesProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string WEBHOOK_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProvideReturnsAPaginatorOfMappedOutputsAndForwardsTheStatusFilter(): void
  {
    $captured = null;
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturnCallback(static function (ListWebhookDeliveriesQuery $query) use (&$captured): ListWebhookDeliveriesResult {
        $captured = $query;

        return new ListWebhookDeliveriesResult(
          items: [
            new WebhookDeliveryResult(
              id: 'delivery-1',
              subscriptionId: self::WEBHOOK_ID,
              eventType: 'intervention.published',
              status: 'failed',
              attempts: 1,
              httpStatus: 500,
              lastError: 'server error',
              nextRetryAt: null,
              deliveredAt: null,
              createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
            ),
          ],
          page: 3,
          itemsPerPage: 5,
          total: 11,
        );
      });

    $provider = new ListWebhookDeliveriesProvider(
      $queryBus,
      $this->authenticatedSecurity(),
      $this->requestStack(['status' => 'failed', 'page' => '3', 'itemsPerPage' => '5']),
      new WebhookDeliveryOutputFactory(),
    );

    $paginator = $provider->provide(new GetCollection(), $this->uriVariables());

    self::assertInstanceOf(TraversablePaginator::class, $paginator);
    self::assertSame(11.0, $paginator->getTotalItems());

    $items = iterator_to_array($paginator);
    self::assertCount(1, $items);
    self::assertInstanceOf(WebhookDeliveryOutput::class, $items[0]);
    self::assertSame('delivery-1', $items[0]->id);
    self::assertSame(500, $items[0]->httpStatus);

    self::assertInstanceOf(ListWebhookDeliveriesQuery::class, $captured);
    self::assertSame(self::USER_ID, $captured->userId);
    self::assertSame(self::WEBHOOK_ID, $captured->subscriptionId);
    self::assertSame('failed', $captured->status);
    self::assertSame(3, $captured->page);
    self::assertSame(5, $captured->itemsPerPage);
  }

  #[Test]
  public function testProvideTreatsAnEmptyStatusAsNoFilter(): void
  {
    $captured = null;
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturnCallback(static function (ListWebhookDeliveriesQuery $query) use (&$captured): ListWebhookDeliveriesResult {
        $captured = $query;

        return new ListWebhookDeliveriesResult([], 1, 30, 0);
      });

    $provider = new ListWebhookDeliveriesProvider(
      $queryBus,
      $this->authenticatedSecurity(),
      $this->requestStack(['status' => '']),
      new WebhookDeliveryOutputFactory(),
    );

    $provider->provide(new GetCollection(), $this->uriVariables());

    self::assertInstanceOf(ListWebhookDeliveriesQuery::class, $captured);
    self::assertNull($captured->status);
    self::assertSame(1, $captured->page);
    self::assertSame(30, $captured->itemsPerPage);
  }

  #[Test]
  public function testProvideRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListWebhookDeliveriesProvider(
      $this->createStub(QueryBusPort::class),
      $security,
      $this->requestStack([]),
      new WebhookDeliveryOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), $this->uriVariables());
  }

  #[Test]
  public function testProvideRequiresBothUriVariables(): void
  {
    $provider = new ListWebhookDeliveriesProvider(
      $this->createStub(QueryBusPort::class),
      $this->authenticatedSecurity(),
      $this->requestStack([]),
      new WebhookDeliveryOutputFactory(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideMapsAMissingSubscriptionToNotFound(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(WebhookSubscriptionNotFoundException::withId(self::WEBHOOK_ID));

    $provider = new ListWebhookDeliveriesProvider(
      $queryBus,
      $this->authenticatedSecurity(),
      $this->requestStack([]),
      new WebhookDeliveryOutputFactory(),
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection(), $this->uriVariables());
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
    ];
  }

  /**
   * Method requestStack.
   *
   * @param array<string, string> $query the query parameters of the current request
   *
   * @return RequestStack a request stack holding a single request
   */
  private function requestStack(array $query): RequestStack
  {
    $stack = new RequestStack();
    $stack->push(new Request($query));

    return $stack;
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
