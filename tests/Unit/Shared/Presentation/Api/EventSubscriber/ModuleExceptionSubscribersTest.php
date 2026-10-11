<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\EventSubscriber;

use ApiPlatform\Metadata\Get;
use Approval\Domain\Exception\ApprovalRequestNotFoundException;
use Assistant\Domain\Exception\AssistantValidationException;
use Calendar\Domain\Exception\CalendarEventValidationException;
use Compliance\Domain\Exception\ComplianceExportNotEntitledException;
use Import\Domain\Exception\ImportAccessDeniedException;
use InvalidArgumentException;
use LogicException;
use Maintenance\Domain\Exception\MaintenanceExportTooLargeException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, HttpExceptionInterface};
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

/**
 * Class ModuleExceptionSubscribersTest
 *
 * Preserves module HTTP statuses and inner failure details without exposing technical wrapper messages.
 *
 * @category Tests
 */
#[CoversClass(\Approval\Presentation\Api\EventSubscriber\ApprovalExceptionSubscriber::class)]
#[CoversClass(\Assistant\Presentation\Api\EventSubscriber\AssistantExceptionSubscriber::class)]
#[CoversClass(\Calendar\Presentation\Api\EventSubscriber\CalendarExceptionSubscriber::class)]
#[CoversClass(\Compliance\Presentation\Api\EventSubscriber\ComplianceExceptionSubscriber::class)]
#[CoversClass(\Import\Presentation\Api\EventSubscriber\ImportExceptionSubscriber::class)]
#[CoversClass(\Maintenance\Presentation\Api\EventSubscriber\MaintenanceExceptionSubscriber::class)]
final class ModuleExceptionSubscribersTest extends TestCase
{
  /**
   * @return iterable<string, array{string, Throwable, int}> representative owner failures and expected statuses
   */
  public static function failures(): iterable
  {
    yield 'approval missing request' => ['Approval', new ApprovalRequestNotFoundException('Approval scope detail'), 404];
    yield 'assistant invalid input' => ['Assistant', new AssistantValidationException('Assistant validation detail'), 422];
    yield 'calendar invalid dates' => ['Calendar', new CalendarEventValidationException('Calendar validation detail'), 422];
    yield 'compliance plan refusal' => ['Compliance', new ComplianceExportNotEntitledException('Compliance entitlement detail'), 403];
    yield 'import access refusal' => ['Import', new ImportAccessDeniedException('Import permission detail'), 403];
    yield 'maintenance export cap' => ['Maintenance', new MaintenanceExportTooLargeException('Maintenance row-cap detail'), 422];
  }

  #[Test]
  #[DataProvider('failures')]
  public function testWrappedFailuresKeepTheirStatusAndDomainMessage(string $module, Throwable $failure, int $status): void
  {
    $wrapped = new RuntimeException('Technical bus wrapper', previous: new RuntimeException('Handler wrapper', previous: $failure));
    $event = $this->event($module, $wrapped);

    $this->subscriber($module)->onKernelException($event);

    $mapped = $event->getThrowable();
    self::assertInstanceOf(HttpExceptionInterface::class, $mapped);
    self::assertSame($status, $mapped->getStatusCode());
    self::assertSame($failure->getMessage(), $mapped->getMessage());
    self::assertSame($wrapped, $mapped->getPrevious());
  }

  #[Test]
  public function testAnAlreadyMappedHttpFailureIsPreserved(): void
  {
    $failure = new AccessDeniedHttpException('Current principal refusal');
    $event = $this->event('Compliance', $failure);

    $this->subscriber('Compliance')->onKernelException($event);

    self::assertSame($failure, $event->getThrowable());
  }

  #[Test]
  public function testSharedErrorsFromAnotherModulesOperationAreNotMapped(): void
  {
    $failure = new InvalidArgumentException('Foreign operation argument');
    $event = $this->event('Calendar', $failure);

    $this->subscriber('Compliance')->onKernelException($event);

    self::assertSame($failure, $event->getThrowable());
  }

  #[Test]
  public function testOperationMetadataProvidesScopeWhenResourceAttributeIsAbsent(): void
  {
    $failure = new ComplianceExportNotEntitledException('Plan entitlement detail');
    $event = $this->event('Compliance', $failure);
    $event->getRequest()->attributes->remove('_api_resource_class');
    $event->getRequest()->attributes->set('_api_operation', new Get(class: 'Compliance\Presentation\Api\Resource\SafetyRegisterExportResource'));

    $this->subscriber('Compliance')->onKernelException($event);

    self::assertInstanceOf(HttpExceptionInterface::class, $event->getThrowable());
    self::assertSame('Plan entitlement detail', $event->getThrowable()->getMessage());
  }

  private function event(string $module, Throwable $failure): ExceptionEvent
  {
    return new ExceptionEvent(
      $this->createStub(HttpKernelInterface::class),
      new Request(attributes: ['_api_resource_class' => $module . '\Presentation\Api\Resource\BoundaryTestResource']),
      HttpKernelInterface::MAIN_REQUEST,
      $failure,
    );
  }

  private function subscriber(string $module): \Approval\Presentation\Api\EventSubscriber\ApprovalExceptionSubscriber|\Assistant\Presentation\Api\EventSubscriber\AssistantExceptionSubscriber|\Calendar\Presentation\Api\EventSubscriber\CalendarExceptionSubscriber|\Compliance\Presentation\Api\EventSubscriber\ComplianceExceptionSubscriber|\Import\Presentation\Api\EventSubscriber\ImportExceptionSubscriber|\Maintenance\Presentation\Api\EventSubscriber\MaintenanceExceptionSubscriber
  {
    return match ($module) {
      'Approval' => new \Approval\Presentation\Api\EventSubscriber\ApprovalExceptionSubscriber(),
      'Assistant' => new \Assistant\Presentation\Api\EventSubscriber\AssistantExceptionSubscriber(),
      'Calendar' => new \Calendar\Presentation\Api\EventSubscriber\CalendarExceptionSubscriber(),
      'Compliance' => new \Compliance\Presentation\Api\EventSubscriber\ComplianceExceptionSubscriber(),
      'Import' => new \Import\Presentation\Api\EventSubscriber\ImportExceptionSubscriber(),
      'Maintenance' => new \Maintenance\Presentation\Api\EventSubscriber\MaintenanceExceptionSubscriber(),
      default => throw new LogicException('Unsupported module exception test scope.'),
    };
  }
}
