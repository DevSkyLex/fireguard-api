<?php

declare(strict_types=1);

namespace Tests\Unit\Mission\Application\Service;

use Mission\Application\Contract\Resource\{MissionResourceSummary, MissionValidationContext, MissionWorkItemSummary};
use Mission\Application\Port\Outbound\MissionResourceGatewayPort;
use Mission\Application\Service\MissionIssueFinder;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_filter;
use function array_values;

final class MissionIssueFinderTest extends TestCase
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
  public function itAppliesMissionTypeValidationPolicy(string $type, string $expectedBlocker): void
  {
    $gateway = $this->createStub(MissionResourceGatewayPort::class);
    $gateway->method('summary')->willReturn(new MissionResourceSummary(0, 0, 0));
    $gateway->method('workItemSummary')->willReturn(new MissionWorkItemSummary(0, 0, 0, 0));
    $gateway->method('validationContext')->willReturn(new MissionValidationContext($type, 'submitted', 'site-1', 'member-1'));
    $gateway->method('equipmentDrafts')->willReturn([]);

    $blockers = array_values(array_filter(
      new MissionIssueFinder($gateway)->find('mission-1'),
      static fn ($issue): bool => 'blocker' === $issue->severity,
    ));

    self::assertContains($expectedBlocker, array_column($blockers, 'message'));
  }
}
