<?php

declare(strict_types=1);

namespace Tests\Support\Http;

use LogicException;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

/**
 * Class ModuleExceptionKernel
 *
 * Exercises the centralized HTTP translation when a unit test directly calls a processor or provider.
 *
 * @category Tests
 */
final class ModuleExceptionKernel
{
  // #region Methods
  /**
   * Method invoke
   *
   * @access public
   *
   * @template T
   *
   * @param string $module owning HTTP module
   * @param callable(): T $operation direct transport invocation
   *
   * @return T transport result, or the centrally translated failure
   */
  public static function invoke(string $module, callable $operation): mixed
  {
    try {
      return $operation();
    } catch (Throwable $failure) {
      $kernel = new class () implements HttpKernelInterface {
        /**
         * {@inheritdoc}
         */
        public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
        {
          throw new LogicException('The module exception test harness never handles an HTTP request.');
        }
      };
      $request = new Request(attributes: ['_api_resource_class' => $module . '\\Presentation\\Api\\Resource\\BoundaryTestResource']);
      $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $failure);
      $subscriber = match ($module) {
        'Approval' => new \Approval\Presentation\Api\EventSubscriber\ApprovalExceptionSubscriber(),
        'Assistant' => new \Assistant\Presentation\Api\EventSubscriber\AssistantExceptionSubscriber(),
        'Calendar' => new \Calendar\Presentation\Api\EventSubscriber\CalendarExceptionSubscriber(),
        'Compliance' => new \Compliance\Presentation\Api\EventSubscriber\ComplianceExceptionSubscriber(),
        'Import' => new \Import\Presentation\Api\EventSubscriber\ImportExceptionSubscriber(),
        'Maintenance' => new \Maintenance\Presentation\Api\EventSubscriber\MaintenanceExceptionSubscriber(),
        default => throw new LogicException('Unsupported module exception test scope.'),
      };
      $subscriber->onKernelException($event);

      throw $event->getThrowable();
    }
  }
  // #endregion
}
