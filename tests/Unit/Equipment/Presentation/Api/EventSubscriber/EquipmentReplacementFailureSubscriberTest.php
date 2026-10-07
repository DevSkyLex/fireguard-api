<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\EventSubscriber;

use Equipment\Domain\Exception\{EquipmentNotFoundException, EquipmentReplacementConflictException, EquipmentSerialNumberAlreadyExistsException};
use Equipment\Presentation\Api\EventSubscriber\EquipmentReplacementFailureSubscriber;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

/**
 * Class EquipmentReplacementFailureSubscriberTest
 *
 * Verifies centralized statuses survive command-bus exception wrappers.
 *
 * @category Unit Tests
 */
final class EquipmentReplacementFailureSubscriberTest extends TestCase
{
  // #region Methods
  /**
   * Method failures
   *
   * @access public
   *
   * @return iterable<string, array{Throwable, int}> mapped replacement failures
   */
  public static function failures(): iterable
  {
    yield 'changed operation' => [EquipmentReplacementConflictException::because('changed'), 409];
    yield 'unavailable equipment' => [EquipmentNotFoundException::withId('hidden'), 404];
    yield 'duplicate serial' => [EquipmentSerialNumberAlreadyExistsException::withSerialNumber('duplicate'), 409];
    yield 'invalid identity' => [InvalidValueException::because('invalid'), 422];
  }

  /**
   * Method preservesTheFailureStatusThroughWrapping
   *
   * @access public
   *
   * @param Throwable $failure the business failure
   * @param int $status the recovery status
   *
   * @return void
   */
  #[Test]
  #[DataProvider('failures')]
  public function preservesTheFailureStatusThroughWrapping(Throwable $failure, int $status): void
  {
    $request = new Request();
    $request->attributes->set('_api_operation_name', 'equipment_replace');
    $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new RuntimeException('wrapped', 0, $failure));
    new EquipmentReplacementFailureSubscriber()->onException($event);
    $response = $event->getResponse();
    self::assertNotNull($response);
    self::assertSame($status, $response->getStatusCode());
    self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
  }

  /**
   * Method leavesOtherOperationsToTheirErrorOwner
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function leavesOtherOperationsToTheirErrorOwner(): void
  {
    $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, EquipmentNotFoundException::withId('hidden'));
    new EquipmentReplacementFailureSubscriber()->onException($event);
    self::assertNull($event->getResponse());
  }
  // #endregion
}
