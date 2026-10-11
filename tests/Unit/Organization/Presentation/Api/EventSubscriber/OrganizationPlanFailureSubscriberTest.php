<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\EventSubscriber;

use InvalidArgumentException;
use Organization\Application\Contract\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\Exception\{OrganizationPlanUsageExceededException, PaidOrganizationPlanRequiresBillingException, PlanNotAvailableException, PlanNotFoundException};
use Organization\Presentation\Api\EventSubscriber\OrganizationPlanFailureSubscriber;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

#[CoversClass(OrganizationPlanFailureSubscriber::class)]
final class OrganizationPlanFailureSubscriberTest extends TestCase
{
  #[Test]
  #[DataProvider('failures')]
  public function mapsKnownFailuresWithoutChangingTheirMessages(Throwable $failure, int $status): void
  {
    $event = $this->event($failure);
    new OrganizationPlanFailureSubscriber()->onException($event);
    self::assertInstanceOf(HttpExceptionInterface::class, $event->getThrowable());
    self::assertSame($status, $event->getThrowable()->getStatusCode());
    self::assertSame($failure->getMessage(), $event->getThrowable()->getMessage());
  }

  #[Test]
  public function retainsUnknownTechnicalFailures(): void
  {
    $failure = new RuntimeException('Outbox unavailable');
    $event = $this->event($failure);
    new OrganizationPlanFailureSubscriber()->onException($event);
    self::assertSame($failure, $event->getThrowable());
  }

  /**
   * @return iterable<string, array{Throwable, int}> the existing status contract
   */
  public static function failures(): iterable
  {
    yield 'permission' => [OrganizationAccessDeniedException::missingPermission('organization.settings.write'), 403];
    yield 'organization' => [OrganizationNotFoundException::withId('org'), 404];
    yield 'plan' => [PlanNotFoundException::withId('plan'), 404];
    yield 'paid plan' => [new PaidOrganizationPlanRequiresBillingException(), 409];
    yield 'overage' => [OrganizationPlanUsageExceededException::withViolations(['members' => ['usage' => 60, 'limit' => 50]]), 409];
    yield 'unavailable' => [PlanNotAvailableException::create(), 400];
    yield 'invalid' => [new InvalidArgumentException('Invalid input'), 400];
  }

  private function event(Throwable $failure): ExceptionEvent
  {
    return new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/api/organizations/org/plan', 'PATCH'), HttpKernelInterface::MAIN_REQUEST, $failure);
  }
}
