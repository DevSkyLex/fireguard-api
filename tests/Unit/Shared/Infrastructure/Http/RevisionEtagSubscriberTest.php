<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Http;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Http\RevisionEtagSubscriber;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Test RevisionEtagSubscriberTest.
 *
 * @category Http Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RevisionEtagSubscriber::class)]
final class RevisionEtagSubscriberTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testItSetsAnEtagFromTheRevisionField(): void
  {
    $response = $this->dispatch(new Response('{"revision":42,"id":"resource-1"}'));

    self::assertSame('"revision-42"', $response->headers->get('ETag'));
  }

  #[Test]
  public function testItAcceptsARevisionOfZero(): void
  {
    $response = $this->dispatch(new Response('{"revision":0}'));

    self::assertSame('"revision-0"', $response->headers->get('ETag'));
  }

  #[Test]
  public function testItLeavesAnExistingEtagUntouched(): void
  {
    $response = new Response('{"revision":42}');
    $response->setEtag('preset');

    $this->dispatch($response);

    self::assertSame('"preset"', $response->headers->get('ETag'));
  }

  #[Test]
  public function testItIgnoresUnsuccessfulResponses(): void
  {
    $response = $this->dispatch(new Response('{"revision":42}', Response::HTTP_INTERNAL_SERVER_ERROR));

    self::assertFalse($response->headers->has('ETag'));
  }

  #[Test]
  public function testItIgnoresAnEmptyBody(): void
  {
    $response = $this->dispatch(new Response(''));

    self::assertFalse($response->headers->has('ETag'));
  }

  #[Test]
  public function testItIgnoresNonJsonBodies(): void
  {
    $response = $this->dispatch(new Response('not json at all'));

    self::assertFalse($response->headers->has('ETag'));
  }

  #[Test]
  public function testItIgnoresAJsonPayloadThatIsNotAnObject(): void
  {
    $response = $this->dispatch(new Response('[1,2,3]'));

    self::assertFalse($response->headers->has('ETag'));
  }

  #[Test]
  public function testItIgnoresAMissingRevisionField(): void
  {
    $response = $this->dispatch(new Response('{"id":"resource-1"}'));

    self::assertFalse($response->headers->has('ETag'));
  }

  #[Test]
  public function testItIgnoresANonIntegerRevision(): void
  {
    $response = $this->dispatch(new Response('{"revision":"42"}'));

    self::assertFalse($response->headers->has('ETag'));
  }
  // #endregion

  // #region Helpers
  private function dispatch(Response $response): Response
  {
    $event = new ResponseEvent(
      $this->createStub(HttpKernelInterface::class),
      new Request(),
      HttpKernelInterface::MAIN_REQUEST,
      $response,
    );

    (new RevisionEtagSubscriber())($event);

    return $event->getResponse();
  }
  // #endregion
}
