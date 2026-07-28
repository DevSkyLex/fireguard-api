<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Presentation\Api\Provider\Feed;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Calendar\Application\Contract\Feed\CalendarFeedItem;
use Calendar\Application\UseCase\Query\Feed\GetCalendarFeed\{GetCalendarFeedQuery, GetCalendarFeedResult};
use Calendar\Presentation\Api\Dto\Output\Feed\CalendarFeedOutput;
use Calendar\Presentation\Api\Provider\Feed\GetCalendarFeedProvider;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

/**
 * Test GetCalendarFeedProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetCalendarFeedProvider::class)]
final class GetCalendarFeedProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProvideAsksTheQueryAndReturnsTheFeedOutput(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $item = new CalendarFeedItem(
      sourceKey: 'intervention',
      id: 'v-1',
      title: 'Extinguisher service',
      description: null,
      startsAt: new DateTimeImmutable('2026-08-05T09:00:00+00:00'),
      endsAt: null,
      allDay: false,
      facilityId: null,
      status: 'planned',
      targetType: 'intervention',
      targetId: 'v-1',
    );

    $captured = null;
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturnCallback(function (GetCalendarFeedQuery $query) use (&$captured, $item): GetCalendarFeedResult {
        $captured = $query;

        return new GetCalendarFeedResult(
          items: [$item],
          from: new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
          to: new DateTimeImmutable('2026-08-31T23:59:59+00:00'),
        );
      });

    $provider = new GetCalendarFeedProvider($queryBus, $security);

    $output = $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID], [
      'filters' => ['from' => '2026-08-01T00:00:00+00:00', 'to' => '2026-08-31T23:59:59+00:00'],
    ]);

    self::assertInstanceOf(GetCalendarFeedQuery::class, $captured);
    self::assertSame(self::USER_ID, $captured->userId);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame('2026-08-01T00:00:00+00:00', $captured->from);
    self::assertSame('2026-08-31T23:59:59+00:00', $captured->to);

    self::assertInstanceOf(CalendarFeedOutput::class, $output);
    self::assertCount(1, $output->items);
    self::assertSame('v-1', $output->items[0]->id);
    self::assertSame('intervention', $output->items[0]->sourceKey);
  }

  #[Test]
  public function testProvideRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetCalendarFeedProvider($this->createStub(QueryBusPort::class), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID], [
      'filters' => ['from' => '2026-08-01T00:00:00+00:00', 'to' => '2026-08-31T23:59:59+00:00'],
    ]);
  }

  #[Test]
  public function testProvideRequiresFromAndToFilters(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $provider = new GetCalendarFeedProvider($this->createStub(QueryBusPort::class), $security);

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID], ['filters' => []]);
  }

  #[Test]
  public function testProvideRequiresAnOrganizationIdUriVariable(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $provider = new GetCalendarFeedProvider($this->createStub(QueryBusPort::class), $security);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('OrganizationId URI parameter is required.');

    $provider->provide(new Get(), [], [
      'filters' => ['from' => '2026-08-01T00:00:00+00:00', 'to' => '2026-08-31T23:59:59+00:00'],
    ]);
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
      new InvalidArgumentException('The "from" filter is not a valid date.'),
    );

    $provider = new GetCalendarFeedProvider($queryBus, $security);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('The "from" filter is not a valid date.');

    $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID], [
      'filters' => ['from' => 'not-a-date', 'to' => '2026-08-31T23:59:59+00:00'],
    ]);
  }
}
