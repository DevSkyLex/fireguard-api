<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Workflow\ListInterventionIssues;

use Intervention\Application\Contract\Resource\InterventionIssue;
use Intervention\Application\Contract\Workflow\InterventionWorkflowContext;
use Intervention\Application\Port\Outbound\{InterventionIssueQueryPort, InterventionWorkflowGatewayPort};
use Intervention\Application\UseCase\Query\Workflow\ListInterventionIssues\{ListInterventionIssuesHandler, ListInterventionIssuesQuery};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListInterventionIssuesHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListInterventionIssuesHandler::class)]
final class ListInterventionIssuesHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string INTERVENTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c10';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  #[Test]
  public function itAuthorizesAndReturnsTheInterventionIssues(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress', null);
    $gateway = $this->createMock(InterventionWorkflowGatewayPort::class);
    $gateway->expects(self::once())->method('interventionContext')->with(self::INTERVENTION_ID)->willReturn($context);

    $found = [new InterventionIssue('error', 'intervention', self::INTERVENTION_ID, 'title', 'Title is required.')];
    $issues = $this->createMock(InterventionIssueQueryPort::class);
    $issues->expects(self::once())->method('issues')->with(self::INTERVENTION_ID)->willReturn($found);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $result = new ListInterventionIssuesHandler($gateway, $issues, $authorization)(
      new ListInterventionIssuesQuery(self::USER_ID, self::INTERVENTION_ID),
    );

    self::assertSame($found, $result->issues);
  }

  #[Test]
  public function itThrowsWhenTheInterventionCannotBeFound(): void
  {
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn(null);
    $issues = $this->createMock(InterventionIssueQueryPort::class);
    $issues->expects(self::never())->method('issues');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $this->expectException(InterventionNotFoundException::class);

    new ListInterventionIssuesHandler($gateway, $issues, $authorization)(
      new ListInterventionIssuesQuery(self::USER_ID, self::INTERVENTION_ID),
    );
  }

  #[Test]
  public function itRejectsAUserMissingTheReadPermission(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress', null);
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn($context);
    $issues = $this->createMock(InterventionIssueQueryPort::class);
    $issues->expects(self::never())->method('issues');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $this->expectException(InterventionAccessDeniedException::class);

    new ListInterventionIssuesHandler($gateway, $issues, $authorization)(
      new ListInterventionIssuesQuery(self::USER_ID, self::INTERVENTION_ID),
    );
  }
}
