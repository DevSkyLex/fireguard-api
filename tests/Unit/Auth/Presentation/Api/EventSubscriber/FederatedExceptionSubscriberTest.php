<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\EventSubscriber;

use Auth\Domain\Exception\Federation\{FederatedAuthException, FederatedConflictException, FederatedUnauthorizedException};
use Auth\Presentation\Api\EventSubscriber\FederatedExceptionSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Verifies that the bus does not change federation denial status or public code.
 *
 * @category Test
 */
final class FederatedExceptionSubscriberTest extends TestCase
{
  /**
   * @return iterable<string, array{FederatedAuthException, int}>
   */
  public static function refusals(): iterable
  {
    yield 'invalid flow' => [new FederatedAuthException('invalid_state', 'Invalid flow.'), 400];
    yield 'identity conflict' => [new FederatedConflictException('identity_already_linked', 'Linked elsewhere.'), 409];
    yield 'account unavailable' => [new FederatedUnauthorizedException('account_unavailable', 'Unavailable.'), 401];
  }

  #[DataProvider('refusals')]
  public function testMapsWrappedRefusals(FederatedAuthException $refusal, int $status): void
  {
    $event = new ExceptionEvent(
      $this->createStub(HttpKernelInterface::class),
      new Request(),
      HttpKernelInterface::MAIN_REQUEST,
      new HandlerFailedException(new Envelope(new stdClass()), [$refusal]),
    );
    new FederatedExceptionSubscriber()->onException($event);
    $mapped = $event->getThrowable();
    self::assertInstanceOf(HttpExceptionInterface::class, $mapped);
    self::assertSame($status, $mapped->getStatusCode());
    self::assertSame($refusal->errorCode, $mapped->getMessage());
    if (401 === $status) {
      self::assertSame('Bearer', $mapped->getHeaders()['WWW-Authenticate']);
    }
  }

  public function testPreservesUnrelatedFailure(): void
  {
    $failure = new RuntimeException('Unrelated error.');
    $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $failure);
    new FederatedExceptionSubscriber()->onException($event);
    self::assertSame($failure, $event->getThrowable());
  }
}
