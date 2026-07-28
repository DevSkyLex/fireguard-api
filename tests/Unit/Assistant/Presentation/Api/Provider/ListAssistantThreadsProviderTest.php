<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Presentation\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Assistant\Application\Contract\Thread\AssistantThreadView;
use Assistant\Application\UseCase\Query\Thread\ListAssistantThreads\{ListAssistantThreadsQuery, ListAssistantThreadsResult};
use Assistant\Domain\Exception\AssistantThreadNotFoundException;
use Assistant\Presentation\Api\Factory\AssistantThreadOutputFactory;
use Assistant\Presentation\Api\Provider\ListAssistantThreadsProvider;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test ListAssistantThreadsProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListAssistantThreadsProvider::class)]
final class ListAssistantThreadsProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProvideReturnsAPaginatorOfTheRequestingUsersOwnThreads(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $captured = null;
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturnCallback(function (ListAssistantThreadsQuery $query) use (&$captured): ListAssistantThreadsResult {
        $captured = $query;

        return new ListAssistantThreadsResult(
          items: [new AssistantThreadView(
            id: 'thread-1',
            organizationId: self::ORGANIZATION_ID,
            memberId: self::USER_ID,
            title: null,
            model: null,
            createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
            updatedAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
            lastMessageAt: null,
          )],
          page: 1,
          itemsPerPage: 30,
          total: 1,
        );
      });

    $provider = new ListAssistantThreadsProvider($queryBus, $security, $requestStack, new AssistantThreadOutputFactory());

    $result = $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(ListAssistantThreadsQuery::class, $captured);
    self::assertSame(self::USER_ID, $captured->actorUserId);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);

    self::assertInstanceOf(TraversablePaginator::class, $result);
    self::assertSame(1.0, $result->getTotalItems());
  }

  #[Test]
  public function testProvideRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListAssistantThreadsProvider(
      $this->createStub(QueryBusPort::class),
      $security,
      new RequestStack(),
      new AssistantThreadOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideRequiresAnOrganizationIdUriVariable(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $provider = new ListAssistantThreadsProvider(
      $this->createStub(QueryBusPort::class),
      $security,
      new RequestStack(),
      new AssistantThreadOutputFactory(),
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('OrganizationId URI parameter is required.');

    $provider->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideMapsADomainFailureToAnHttpException(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(
      AssistantThreadNotFoundException::withId('thread-1'),
    );

    $provider = new ListAssistantThreadsProvider(
      $queryBus,
      $security,
      new RequestStack(),
      new AssistantThreadOutputFactory(),
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }
}
