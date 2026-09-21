<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Presentation\Api\Controller;

use Calendar\Application\UseCase\Query\Feed\GetCalendarFeed\GetCalendarFeedResult;
use Calendar\Application\UseCase\Query\FeedToken\ResolveCalendarFeedToken\ResolveCalendarFeedTokenResult;
use Calendar\Presentation\Api\Controller\GetCalendarFeedIcsController;
use Calendar\Presentation\Api\Ical\CalendarFeedIcalWriter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(GetCalendarFeedIcsController::class)]
final class GetCalendarFeedIcsControllerTest extends TestCase
{
  #[Test]
  public function incompleteFeedDoesNotPublishAnEmptyCalendarAsSuccessfulSynchronization(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::exactly(2))->method('ask')->willReturnOnConsecutiveCalls(
      new ResolveCalendarFeedTokenResult('org', 'user', '2026-09-01T00:00:00Z', '2026-10-01T00:00:00Z'),
      new GetCalendarFeedResult([], new DateTimeImmutable('2026-09-01'), new DateTimeImmutable('2026-10-01'), complete: false),
    );
    $request = Request::create('/api/calendar/feed/private-token.ics');
    $request->attributes->set('token', 'private-token');
    $response = (new GetCalendarFeedIcsController($bus, new CalendarFeedIcalWriter('https://app.test')))($request);

    self::assertSame(503, $response->getStatusCode());
    self::assertSame('300', $response->headers->get('Retry-After'));
    self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    self::assertStringNotContainsString('BEGIN:VCALENDAR', (string) $response->getContent());
    self::assertStringNotContainsString('private-token', (string) $response->getContent());
  }
}
