<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Activity\ListInterventionActivities;

use Intervention\Application\Contract\Workflow\{InterventionWorkflowContext, InterventionWorkflowPage};
use Intervention\Application\Port\Outbound\{InterventionActivityPort, InterventionWorkflowGatewayPort};
use Intervention\Application\UseCase\Query\Activity\ListInterventionActivities\{ListInterventionActivitiesHandler, ListInterventionActivitiesQuery};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListInterventionActivitiesHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListInterventionActivitiesHandler::class)]
final class ListInterventionActivitiesHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string INTERVENTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c10';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  #[Test]
  public function itAuthorizesAndReturnsTheActivityPage(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress', null);
    $gateway = $this->createMock(InterventionWorkflowGatewayPort::class);
    $gateway->expects(self::once())->method('interventionContext')->with(self::INTERVENTION_ID)->willReturn($context);

    $page = new InterventionWorkflowPage([], 1, 30, 0);
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::once())
      ->method('listByIntervention')
      ->with(self::INTERVENTION_ID, 1, 30)
      ->willReturn($page);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $result = new ListInterventionActivitiesHandler($gateway, $activities, $authorization)(
      new ListInterventionActivitiesQuery(self::USER_ID, self::INTERVENTION_ID, 1, 30),
    );

    self::assertSame($page, $result->page);
  }

  #[Test]
  public function itThrowsWhenTheInterventionCannotBeFound(): void
  {
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn(null);
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::never())->method('listByIntervention');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $this->expectException(InterventionNotFoundException::class);

    new ListInterventionActivitiesHandler($gateway, $activities, $authorization)(
      new ListInterventionActivitiesQuery(self::USER_ID, self::INTERVENTION_ID, 1, 30),
    );
  }

  #[Test]
  public function itRejectsAUserMissingTheReadPermission(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress', null);
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn($context);
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::never())->method('listByIntervention');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $this->expectException(InterventionAccessDeniedException::class);

    new ListInterventionActivitiesHandler($gateway, $activities, $authorization)(
      new ListInterventionActivitiesQuery(self::USER_ID, self::INTERVENTION_ID, 1, 30),
    );
  }
}
