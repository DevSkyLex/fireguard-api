<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Report\ResolveInspectionReportAccess;

use Inspection\Application\Port\Outbound\InspectionReportEntitlementPort;
use Inspection\Application\UseCase\Query\Report\ResolveInspectionReportAccess\{ResolveInspectionReportAccessHandler, ResolveInspectionReportAccessQuery};
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionNotFoundException, InspectionReportNotEntitledException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(ResolveInspectionReportAccessHandler::class)]
final class ResolveInspectionReportAccessHandlerTest extends TestCase
{
  /**
   * @param class-string<Throwable> $exception
   */
  #[Test]
  #[DataProvider('refusals')]
  public function refusesScopeBeforeLookingUpThePlan(OrganizationAccessDecision $decision, string $exception, string $message): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);
    $entitlement = $this->createMock(InspectionReportEntitlementPort::class);
    $entitlement->expects(self::never())->method('isExportEntitled');
    $entitlement->expects(self::never())->method('resolvePlanKey');
    $this->expectException($exception);
    $this->expectExceptionMessage($message);
    new ResolveInspectionReportAccessHandler($authorization, $entitlement)(new ResolveInspectionReportAccessQuery('org', 'actor'));
  }

  /**
   * @return iterable<string, array{OrganizationAccessDecision, class-string<Throwable>, string}>
   */
  public static function refusals(): iterable
  {
    yield 'scope' => [OrganizationAccessDecision::OUTSIDE_SCOPE, InspectionNotFoundException::class, 'Organization not found.'];
    yield 'permission' => [OrganizationAccessDecision::MISSING_PERMISSION, InspectionAccessDeniedException::class, 'Missing organization.inspection.read permission.'];
  }

  #[Test]
  public function refusesThePlanAfterReadAccess(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $entitlement = $this->createStub(InspectionReportEntitlementPort::class);
    $entitlement->method('isExportEntitled')->willReturn(false);
    $this->expectException(InspectionReportNotEntitledException::class);
    new ResolveInspectionReportAccessHandler($authorization, $entitlement)(new ResolveInspectionReportAccessQuery('org', 'actor'));
  }

  #[Test]
  public function retainsUnknownPlanFallbackForTheExportEvent(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $entitlement = $this->createStub(InspectionReportEntitlementPort::class);
    $entitlement->method('isExportEntitled')->willReturn(true);
    $result = new ResolveInspectionReportAccessHandler($authorization, $entitlement)(new ResolveInspectionReportAccessQuery('org', 'actor'));
    self::assertSame('unknown', $result->planKey);
  }
}
