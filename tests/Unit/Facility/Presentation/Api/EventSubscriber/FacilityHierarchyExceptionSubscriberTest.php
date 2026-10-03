<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\EventSubscriber;

use Facility\Domain\Exception\{FacilityHierarchyException, FacilityRevisionMismatchException};
use Facility\Presentation\Api\EventSubscriber\FacilityHierarchyExceptionSubscriber;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, HttpExceptionInterface};
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Test FacilityHierarchyExceptionSubscriberTest.
 *
 * @category EventSubscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityHierarchyExceptionSubscriberTest extends TestCase
{
  public function testLegacyBadRequestCannotHideHierarchyViolation(): void
  {
    $domain = FacilityHierarchyException::incompatibleParentType('floor', 'site');
    $event = new ExceptionEvent(
      $this->createStub(HttpKernelInterface::class),
      new Request(),
      HttpKernelInterface::MAIN_REQUEST,
      new BadRequestHttpException('Legacy processor', MessengerRuntimeException::wrap($domain)),
    );
    new FacilityHierarchyExceptionSubscriber()->onException($event);
    self::assertInstanceOf(HttpExceptionInterface::class, $event->getThrowable());
    self::assertSame(422, $event->getThrowable()->getStatusCode());
    self::assertSame($domain->getMessage(), $event->getThrowable()->getMessage());
  }

  public function testTransactionRevisionMismatchRemainsAPreconditionFailure(): void
  {
    $event = new ExceptionEvent(
      $this->createStub(HttpKernelInterface::class),
      new Request(),
      HttpKernelInterface::MAIN_REQUEST,
      MessengerRuntimeException::wrap(FacilityRevisionMismatchException::stale()),
    );
    new FacilityHierarchyExceptionSubscriber()->onException($event);
    self::assertInstanceOf(HttpExceptionInterface::class, $event->getThrowable());
    self::assertSame(412, $event->getThrowable()->getStatusCode());
  }
}
