<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Presentation\Api\Controller;

use Calendar\Application\UseCase\Command\Feed\ReadCalendarFeedIcs\{ReadCalendarFeedIcsCommand, ReadCalendarFeedIcsResult};
use Calendar\Domain\Exception\CalendarFeedTokenNotFoundException;
use Calendar\Presentation\Api\Controller\GetCalendarFeedIcsController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[CoversClass(GetCalendarFeedIcsController::class)]
final class GetCalendarFeedIcsControllerTest extends TestCase
{
  public function testOriginalDocumentBytesAreRenderedWithoutASecondProjection(): void
  {
    $document = "BEGIN:VCALENDAR\r\nDTSTART;VALUE=DATE:20260329\r\nEND:VCALENDAR\r\n";
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ReadCalendarFeedIcsCommand $command): bool => 'private-token' === $command->secret))->willReturn(new ReadCalendarFeedIcsResult($document));
    $response = (new GetCalendarFeedIcsController($bus))($this->request());
    self::assertSame(200, $response->getStatusCode());
    self::assertSame($document, $response->getContent());
    self::assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));
  }

  public function testIncompleteOutcomePreservesItsOriginalStatusAndRetryHeaders(): void
  {
    $bus = $this->createStub(CommandBusPort::class);
    $bus->method('dispatch')->willReturn(new ReadCalendarFeedIcsResult('Calendar feed temporarily incomplete.', 503, 'text/plain; charset=utf-8', 'private, no-store', '300'));
    $response = (new GetCalendarFeedIcsController($bus))($this->request());
    self::assertSame(503, $response->getStatusCode());
    self::assertSame('300', $response->headers->get('Retry-After'));
    self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    self::assertStringNotContainsString('private-token', (string) $response->getContent());
  }

  public function testCurrentOwnerDenialKeepsTheUniformNotFoundContract(): void
  {
    $bus = $this->createStub(CommandBusPort::class);
    $bus->method('dispatch')->willThrowException(new CalendarFeedTokenNotFoundException());
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Calendar feed token not found.');
    (new GetCalendarFeedIcsController($bus))($this->request());
  }

  private function request(): Request
  {
    $request = Request::create('/api/calendar/feed/private-token.ics');
    $request->attributes->set('token', 'private-token');

    return $request;
  }
}
