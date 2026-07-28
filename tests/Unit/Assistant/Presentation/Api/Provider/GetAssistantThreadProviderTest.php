<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use Assistant\Application\Contract\Message\AssistantMessageView;
use Assistant\Application\Contract\Thread\AssistantThreadView;
use Assistant\Application\UseCase\Query\Thread\GetAssistantThread\{GetAssistantThreadQuery, GetAssistantThreadResult};
use Assistant\Domain\Exception\AssistantThreadNotFoundException;
use Assistant\Presentation\Api\Factory\{AssistantMessageOutputFactory, AssistantThreadOutputFactory};
use Assistant\Presentation\Api\Provider\GetAssistantThreadProvider;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test GetAssistantThreadProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetAssistantThreadProvider::class)]
final class GetAssistantThreadProviderTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string THREAD_ID = 'thread-1';

  private const string USER_ID = 'user-id';
  // #endregion

  // #region Methods
  #[Test]
  public function testProvideReturnsTheThreadWithItsMessages(): void
  {
    $captured = null;
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturnCallback(function (GetAssistantThreadQuery $query) use (&$captured): GetAssistantThreadResult {
        $captured = $query;

        return new GetAssistantThreadResult(
          thread: $this->threadView(),
          messages: [$this->messageView('message-1'), $this->messageView('message-2')],
          messagesPage: 2,
          messagesItemsPerPage: 25,
          messagesTotal: 40,
        );
      });

    $provider = $this->provider($queryBus, $this->requestStack(['messagesPage' => '2', 'messagesItemsPerPage' => '25']));

    $output = $provider->provide(new Get(), [
      'organizationId' => self::ORGANIZATION_ID,
      'threadId' => self::THREAD_ID,
    ]);

    self::assertInstanceOf(GetAssistantThreadQuery::class, $captured);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame(self::THREAD_ID, $captured->threadId);
    self::assertSame(self::USER_ID, $captured->actorUserId);
    self::assertSame(2, $captured->messagesPage);
    self::assertSame(25, $captured->messagesItemsPerPage);

    self::assertSame(self::THREAD_ID, $output->id);
    self::assertSame(self::ORGANIZATION_ID, $output->organizationId);
    self::assertSame(self::USER_ID, $output->memberId);
    self::assertSame('Fire safety questions', $output->title);
    self::assertCount(2, $output->messages);
    self::assertSame('message-1', $output->messages[0]->id);
    self::assertSame(2, $output->messagesPage);
    self::assertSame(25, $output->messagesItemsPerPage);
    self::assertSame(40, $output->messagesTotal);
  }

  #[Test]
  public function testProvideFallsBackToDefaultPaginationWithoutARequest(): void
  {
    $captured = null;
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturnCallback(function (GetAssistantThreadQuery $query) use (&$captured): GetAssistantThreadResult {
        $captured = $query;

        return new GetAssistantThreadResult(
          thread: $this->threadView(),
          messages: [],
          messagesPage: 1,
          messagesItemsPerPage: 50,
          messagesTotal: 0,
        );
      });

    $provider = $this->provider($queryBus, new RequestStack());

    $provider->provide(new Get(), [
      'organizationId' => self::ORGANIZATION_ID,
      'threadId' => self::THREAD_ID,
    ]);

    self::assertInstanceOf(GetAssistantThreadQuery::class, $captured);
    self::assertSame(1, $captured->messagesPage);
    self::assertSame(50, $captured->messagesItemsPerPage);
  }

  #[Test]
  public function testProvideClampsPaginationToTheAllowedBounds(): void
  {
    $captured = null;
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturnCallback(function (GetAssistantThreadQuery $query) use (&$captured): GetAssistantThreadResult {
        $captured = $query;

        return new GetAssistantThreadResult(
          thread: $this->threadView(),
          messages: [],
          messagesPage: 1,
          messagesItemsPerPage: 200,
          messagesTotal: 0,
        );
      });

    $provider = $this->provider(
      $queryBus,
      $this->requestStack(['messagesPage' => '-3', 'messagesItemsPerPage' => '9999']),
    );

    $provider->provide(new Get(), [
      'organizationId' => self::ORGANIZATION_ID,
      'threadId' => self::THREAD_ID,
    ]);

    self::assertInstanceOf(GetAssistantThreadQuery::class, $captured);
    self::assertSame(1, $captured->messagesPage);
    self::assertSame(200, $captured->messagesItemsPerPage);
  }

  #[Test]
  public function testProvideRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetAssistantThreadProvider(
      $this->createStub(QueryBusPort::class),
      $security,
      new RequestStack(),
      new AssistantThreadOutputFactory(),
      new AssistantMessageOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), [
      'organizationId' => self::ORGANIZATION_ID,
      'threadId' => self::THREAD_ID,
    ]);
  }

  #[Test]
  public function testProvideRequiresBothUriVariables(): void
  {
    $provider = $this->provider($this->createStub(QueryBusPort::class), new RequestStack());

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideMapsDomainExceptions(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(AssistantThreadNotFoundException::withId(self::THREAD_ID));

    $provider = $this->provider($queryBus, new RequestStack());

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), [
      'organizationId' => self::ORGANIZATION_ID,
      'threadId' => self::THREAD_ID,
    ]);
  }
  // #endregion

  // #region Helpers
  private function provider(QueryBusPort $queryBus, RequestStack $requestStack): GetAssistantThreadProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return new GetAssistantThreadProvider(
      $queryBus,
      $security,
      $requestStack,
      new AssistantThreadOutputFactory(),
      new AssistantMessageOutputFactory(),
    );
  }

  /**
   * @param array<string, string> $query
   */
  private function requestStack(array $query): RequestStack
  {
    $stack = new RequestStack();
    $stack->push(new Request($query));

    return $stack;
  }

  private function threadView(): AssistantThreadView
  {
    return new AssistantThreadView(
      id: self::THREAD_ID,
      organizationId: self::ORGANIZATION_ID,
      memberId: self::USER_ID,
      title: 'Fire safety questions',
      model: null,
      createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-07-18T01:00:00+00:00'),
      lastMessageAt: null,
    );
  }

  private function messageView(string $id): AssistantMessageView
  {
    return new AssistantMessageView(
      id: $id,
      threadId: self::THREAD_ID,
      organizationId: self::ORGANIZATION_ID,
      role: 'assistant',
      body: 'An answer.',
      status: 'complete',
      errorCode: null,
      tokenCount: 8,
      createdAt: new DateTimeImmutable('2026-07-18T00:30:00+00:00'),
      completedAt: new DateTimeImmutable('2026-07-18T00:30:01+00:00'),
    );
  }
  // #endregion
}
