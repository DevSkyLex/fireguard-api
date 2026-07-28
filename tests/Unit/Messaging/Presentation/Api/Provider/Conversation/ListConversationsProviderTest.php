<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Conversation;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\{ConversationPage, ConversationView};
use Messaging\Application\UseCase\Query\Conversation\ListConversations\{ListConversationsQuery, ListConversationsResult};
use Messaging\Domain\Exception\MessagingAccessDeniedException;
use Messaging\Presentation\Api\Dto\Output\ConversationOutput;
use Messaging\Presentation\Api\Factory\ConversationOutputFactory;
use Messaging\Presentation\Api\Provider\Conversation\ListConversationsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function iterator_to_array;

/**
 * Test ListConversationsProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListConversationsProvider::class)]
final class ListConversationsProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655441500';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441501';

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListConversationsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      mapper: new ConversationOutputFactory(),
      security: $security,
      requestStack: $this->requestStack(['organization' => self::ORG_ID]),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideThrowsWhenTheOrganizationFilterIsMissing(): void
  {
    $provider = new ListConversationsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      mapper: new ConversationOutputFactory(),
      security: $this->securityWithUser(),
      requestStack: $this->requestStack([]),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideForwardsTheFiltersAndPaginatesTheOutputs(): void
  {
    $now = new DateTimeImmutable('2026-01-01T09:00:00+00:00');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListConversationsQuery $query): bool => self::USER_ID === $query->userId
        && self::ORG_ID === $query->organizationId
        && 'facility' === $query->subjectType
        && 'facility-1' === $query->subjectId
        && false === $query->isArchived
        && true === $query->unreadOnly
        && 2 === $query->page
        && 10 === $query->itemsPerPage))
      ->willReturn(new ListConversationsResult(
        page: new ConversationPage(
          items: [new ConversationView('conv-1', self::ORG_ID, 'facility', 'facility-1', 'subject', null, 3, false, $now, $now)],
          page: 2,
          itemsPerPage: 10,
          total: 12,
        ),
        unreadCounts: ['conv-1' => 5],
        favoriteConversationIds: ['conv-1'],
      ));

    $provider = new ListConversationsProvider(
      queryBus: $queryBus,
      mapper: new ConversationOutputFactory(),
      security: $this->securityWithUser(),
      requestStack: $this->requestStack([
        'organization' => '/api/organizations/' . self::ORG_ID,
        'subjectType' => 'facility',
        'subjectId' => 'facility-1',
        'isArchived' => '0',
        'unreadOnly' => '1',
        'page' => '2',
        'itemsPerPage' => '10',
      ]),
    );

    $result = $provider->provide(new GetCollection());

    self::assertInstanceOf(TraversablePaginator::class, $result);
    self::assertSame(12.0, $result->getTotalItems());
    self::assertSame(2.0, $result->getCurrentPage());
    self::assertSame(10.0, $result->getItemsPerPage());

    $items = iterator_to_array($result);
    self::assertCount(1, $items);
    self::assertInstanceOf(ConversationOutput::class, $items[0]);
    self::assertSame('conv-1', $items[0]->id);
    self::assertSame(5, $items[0]->unreadCount);
    self::assertTrue($items[0]->isFavorite);
  }

  #[Test]
  public function testProvideMapsADomainFailureToItsHttpCounterpart(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new MessagingAccessDeniedException('Not a member.'));

    $provider = new ListConversationsProvider(
      queryBus: $queryBus,
      mapper: new ConversationOutputFactory(),
      security: $this->securityWithUser(),
      requestStack: $this->requestStack(['organization' => self::ORG_ID]),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  /**
   * @param array<string, string> $query
   */
  private function requestStack(array $query): RequestStack
  {
    $requestStack = new RequestStack();
    $requestStack->push(new Request($query));

    return $requestStack;
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }
}
