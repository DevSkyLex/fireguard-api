<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\EventSubscriber;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Presentation\Api\EventSubscriber\BusFailureUnwrappingSubscriber;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{ConflictHttpException, NotFoundHttpException};
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Test BusFailureUnwrappingSubscriberTest.
 *
 * @category EventSubscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(BusFailureUnwrappingSubscriber::class)]
final class BusFailureUnwrappingSubscriberTest extends TestCase
{
  #[Test]
  public function testItRunsAheadOfTheModuleSubscribers(): void
  {
    // The three existing exception subscribers sit at 10 and Symfony's security
    // ExceptionListener at 2. Landing behind any of them would mean they still
    // inspect the envelope rather than the exception.
    self::assertGreaterThan(10, BusFailureUnwrappingSubscriber::PRIORITY);
  }

  #[Test]
  public function testItReplacesTheDoubleEnvelopeWithTheDomainException(): void
  {
    // The real shape: Messenger wraps the handler's throw in
    // HandlerFailedException, then MessengerCommandBusAdapter wraps that.
    $domain = new RuntimeException('Organization not found.');
    $event = $this->eventFor(MessengerRuntimeException::wrap(
      exception: new HandlerFailedException(new Envelope(new stdClass()), [$domain]),
    ));

    new BusFailureUnwrappingSubscriber()->onKernelException($event);

    self::assertSame($domain, $event->getThrowable());
  }

  #[Test]
  public function testItLeavesAnAlreadyMappedFailureAlone(): void
  {
    // A processor that caught and mapped has spoken. Re-deciding here would
    // silently override every explicit mapping still in place — which is what
    // lets the two mechanisms coexist during the migration.
    $mapped = new NotFoundHttpException('Not found.', new RuntimeException('inner'));
    $event = $this->eventFor($mapped);

    new BusFailureUnwrappingSubscriber()->onKernelException($event);

    self::assertSame($mapped, $event->getThrowable());
  }

  #[Test]
  public function testItLeavesAnUnwrappedExceptionAlone(): void
  {
    // A guard that threw before the dispatch has no envelope. Touching it would
    // be inventing a change where there is nothing to unwrap.
    $bare = new RuntimeException('raised before dispatch');
    $event = $this->eventFor($bare);

    new BusFailureUnwrappingSubscriber()->onKernelException($event);

    self::assertSame($bare, $event->getThrowable());
  }

  #[Test]
  public function testItSurvivesAnEmptyEnvelope(): void
  {
    $empty = MessengerRuntimeException::wrap(exception: new RuntimeException('cause'));
    $event = $this->eventFor($empty);

    new BusFailureUnwrappingSubscriber()->onKernelException($event);

    self::assertNotSame($empty, $event->getThrowable());
  }

  #[Test]
  public function testItDoesNotUnwrapPastTheFirstDomainException(): void
  {
    // A domain exception that carries a cause of its own keeps it. Peeling
    // further would surrender the class the mapping keys on.
    $root = new RuntimeException('database is down');
    $domain = new ConflictHttpException('state conflict', $root);
    $event = $this->eventFor(MessengerRuntimeException::wrap(exception: $domain));

    new BusFailureUnwrappingSubscriber()->onKernelException($event);

    self::assertSame($domain, $event->getThrowable());
  }

  private function eventFor(Throwable $throwable): ExceptionEvent
  {
    return new ExceptionEvent(
      $this->createStub(HttpKernelInterface::class),
      new Request(),
      HttpKernelInterface::MAIN_REQUEST,
      $throwable,
    );
  }
}
