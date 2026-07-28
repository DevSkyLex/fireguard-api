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
use Webhook\Application\UseCase\Query\Subscription\GetWebhookSubscription\GetWebhookSubscriptionResult;
use Webhook\Application\UseCase\Query\Subscription\ListWebhookSubscriptions\{ListWebhookSubscriptionsQuery, ListWebhookSubscriptionsResult};
use Webhook\Domain\Exception\WebhookSubscriptionNotFoundException;
use Webhook\Presentation\Api\Dto\Output\WebhookSubscriptionOutput;
use Webhook\Presentation\Api\Factory\WebhookSubscriptionOutputFactory;
use Webhook\Presentation\Api\Provider\ListWebhookSubscriptionsProvider;

use function iterator_to_array;

/**
 * Test ListWebhookSubscriptionsProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListWebhookSubscriptionsProvider::class)]
final class ListWebhookSubscriptionsProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProvideReturnsAPaginatorOfMappedOutputs(): void
  {
    $captured = null;
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturnCallback(static function (ListWebhookSubscriptionsQuery $query) use (&$captured): ListWebhookSubscriptionsResult {
        $captured = $query;

        return new ListWebhookSubscriptionsResult(
          items: [
            new GetWebhookSubscriptionResult(
              id: 'sub-1',
              organizationId: self::ORGANIZATION_ID,
              url: 'https://example.com/hook',
              eventTypes: ['intervention.published'],
              isActive: true,
              description: 'Integration',
              createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
              updatedAt: new DateTimeImmutable('2026-07-18T01:00:00+00:00'),
            ),
          ],
          page: 2,
          itemsPerPage: 10,
          total: 42,
        );
      });

    $provider = new ListWebhookSubscriptionsProvider(
      $queryBus,
      $this->authenticatedSecurity(),
      $this->requestStack(['page' => '2', 'itemsPerPage' => '10']),
      new WebhookSubscriptionOutputFactory(),
    );

    $paginator = $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(TraversablePaginator::class, $paginator);
    self::assertSame(42.0, $paginator->getTotalItems());
    self::assertSame(2.0, $paginator->getCurrentPage());
    self::assertSame(10.0, $paginator->getItemsPerPage());

    $items = iterator_to_array($paginator);
    self::assertCount(1, $items);
    self::assertInstanceOf(WebhookSubscriptionOutput::class, $items[0]);
    self::assertSame('sub-1', $items[0]->id);

    self::assertInstanceOf(ListWebhookSubscriptionsQuery::class, $captured);
    self::assertSame(self::USER_ID, $captured->userId);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame(2, $captured->page);
    self::assertSame(10, $captured->itemsPerPage);
  }

  #[Test]
  public function testProvideClampsThePaginationParameters(): void
  {
    $captured = null;
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturnCallback(static function (ListWebhookSubscriptionsQuery $query) use (&$captured): ListWebhookSubscriptionsResult {
        $captured = $query;

        return new ListWebhookSubscriptionsResult([], 1, 100, 0);
      });

    $provider = new ListWebhookSubscriptionsProvider(
      $queryBus,
      $this->authenticatedSecurity(),
      $this->requestStack(['page' => '-5', 'itemsPerPage' => '500']),
      new WebhookSubscriptionOutputFactory(),
    );

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(ListWebhookSubscriptionsQuery::class, $captured);
    self::assertSame(1, $captured->page);
    self::assertSame(100, $captured->itemsPerPage);
  }

  #[Test]
  public function testProvideRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListWebhookSubscriptionsProvider(
      $this->createStub(QueryBusPort::class),
      $security,
      $this->requestStack([]),
      new WebhookSubscriptionOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideRequiresAnOrganizationIdUriVariable(): void
  {
    $provider = new ListWebhookSubscriptionsProvider(
      $this->createStub(QueryBusPort::class),
      $this->authenticatedSecurity(),
      $this->requestStack([]),
      new WebhookSubscriptionOutputFactory(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideMapsDomainExceptionsToHttpExceptions(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new WebhookSubscriptionNotFoundException('sub-1'));

    $provider = new ListWebhookSubscriptionsProvider(
      $queryBus,
      $this->authenticatedSecurity(),
      $this->requestStack([]),
      new WebhookSubscriptionOutputFactory(),
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
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
