<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use Intervention\Application\Contract\Resource\{InterventionResourceSummary, InterventionValidationContext, InterventionWorkItemSummary};
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionIssueFinder;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_filter;
use function array_values;

final class InterventionIssueFinderTest extends TestCase
{
  /**
   * @return iterable<string, array{string, string}>
   */
  public static function emptyScopeRules(): iterable
  {
    yield 'site setup warns about work items but blocks missing facility' => ['site_setup', 'At least one facility is required.'];
    yield 'inventory blocks missing explicit scope' => ['inventory', 'No explicit work item has been prepared yet.'];
    yield 'inspection campaign blocks missing explicit scope' => ['inspection_campaign', 'No explicit work item has been prepared yet.'];
  }

  #[Test]
  #[DataProvider('emptyScopeRules')]
  public function itAppliesInterventionTypeValidationPolicy(string $type, string $expectedBlocker): void
  {
    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('summary')->willReturn(new InterventionResourceSummary(0, 0, 0));
    $gateway->method('workItemSummary')->willReturn(new InterventionWorkItemSummary(0, 0, 0, 0));
    $gateway->method('validationContext')->willReturn(new InterventionValidationContext($type, 'submitted', 'site-1', 'member-1'));
    $gateway->method('equipmentDrafts')->willReturn([]);

    $blockers = array_values(array_filter(
      new InterventionIssueFinder($gateway)->find('intervention-1'),
      static fn ($issue): bool => 'blocker' === $issue->severity,
    ));

    self::assertContains($expectedBlocker, array_column($blockers, 'message'));
  }
}
