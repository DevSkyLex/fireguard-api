<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use Assistant\Application\Contract\Thread\AssistantThreadView;
use Assistant\Application\UseCase\Query\Thread\GetAssistantThread\{GetAssistantThreadQuery, GetAssistantThreadResult};
use Assistant\Domain\Exception\AssistantThreadNotFoundException;
use Assistant\Presentation\Api\Dto\Output\AssistantThreadSubscriptionOutput;
use Assistant\Presentation\Api\Provider\GetAssistantThreadSubscriptionProvider;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

/**
 * Test GetAssistantThreadSubscriptionProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetAssistantThreadSubscriptionProvider::class)]
final class GetAssistantThreadSubscriptionProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64e01';

  private const string THREAD_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64e02';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProvideRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetAssistantThreadSubscriptionProvider(
      $this->createStub(QueryBusPort::class),
      $security,
      $this->createStub(TokenFactoryInterface::class),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID, 'threadId' => self::THREAD_ID]);
  }

  #[Test]
  public function testProvideRequiresUriVariables(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $provider = new GetAssistantThreadSubscriptionProvider(
      $this->createStub(QueryBusPort::class),
      $security,
      $this->createStub(TokenFactoryInterface::class),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new Get(), []);
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
      AssistantThreadNotFoundException::withId(self::THREAD_ID),
    );

    $provider = new GetAssistantThreadSubscriptionProvider(
      $queryBus,
      $security,
      $this->createStub(TokenFactoryInterface::class),
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), [
      'organizationId' => self::ORGANIZATION_ID,
      'threadId' => self::THREAD_ID,
    ]);
  }

  #[Test]
  public function testProvideRunsTheReadQueryThenMintsAScopedToken(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $thread = new AssistantThreadView(
      id: self::THREAD_ID,
      organizationId: self::ORGANIZATION_ID,
      memberId: self::USER_ID,
      title: null,
      model: null,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      lastMessageAt: null,
    );

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetAssistantThreadQuery $query): bool {
        self::assertSame(self::ORGANIZATION_ID, $query->organizationId);
        self::assertSame(self::THREAD_ID, $query->threadId);
        self::assertSame(self::USER_ID, $query->actorUserId);

        return true;
      }))
      ->willReturn(new GetAssistantThreadResult($thread, [], 1, 1, 0));

    $tokenFactory = $this->createMock(TokenFactoryInterface::class);
    $tokenFactory->expects(self::once())
      ->method('create')
      ->with(
        ['/organizations/' . self::ORGANIZATION_ID . '/assistant/threads/' . self::THREAD_ID],
        [],
        self::callback(static function (array $claims): bool {
          self::assertArrayHasKey('exp', $claims);
          self::assertInstanceOf(DateTimeImmutable::class, $claims['exp']);

          // Minted with the 60s TTL passed below, so it must expire inside the
          // next minute — proving the token is short-lived rather than eternal.
          $secondsFromNow = $claims['exp']->getTimestamp() - new DateTimeImmutable()->getTimestamp();
          self::assertGreaterThan(0, $secondsFromNow);
          self::assertLessThanOrEqual(60, $secondsFromNow);

          return true;
        }),
      )
      ->willReturn('jwt-token');

    $provider = new GetAssistantThreadSubscriptionProvider($queryBus, $security, $tokenFactory, tokenTtl: 60);

    $output = $provider->provide(new Get(), [
      'organizationId' => self::ORGANIZATION_ID,
      'threadId' => self::THREAD_ID,
    ]);

    self::assertInstanceOf(AssistantThreadSubscriptionOutput::class, $output);
    self::assertSame('jwt-token', $output->token);
    self::assertSame('/organizations/' . self::ORGANIZATION_ID . '/assistant/threads/' . self::THREAD_ID, $output->topic);
  }
}
