<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use Intervention\Application\Contract\Resource\{InterventionEquipmentDraft, InterventionResourceSummary, InterventionValidationContext, InterventionWorkItemSummary};
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

  #[Test]
  public function itFlagsMissingInitialInspectionForSiteSetupWithEquipment(): void
  {
    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('summary')->willReturn(new InterventionResourceSummary(1, 3, 0));
    $gateway->method('workItemSummary')->willReturn(new InterventionWorkItemSummary(4, 0, 0, 0));
    $gateway->method('validationContext')->willReturn(new InterventionValidationContext('site_setup', 'submitted', 'site-1', 'member-1'));
    $gateway->method('equipmentDrafts')->willReturn([]);

    $messages = array_column(new InterventionIssueFinder($gateway)->find('intervention-1'), 'message');

    self::assertContains('No initial inspection has been recorded yet.', $messages);
    self::assertNotContains('At least one facility is required.', $messages);
    self::assertNotContains('No equipment has been inventoried yet.', $messages);
    self::assertNotContains('No explicit work item has been prepared yet.', $messages);
  }

  #[Test]
  public function itReportsIncompleteAndSkippedWorkItems(): void
  {
    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('summary')->willReturn(new InterventionResourceSummary(1, 2, 1));
    $gateway->method('workItemSummary')->willReturn(new InterventionWorkItemSummary(3, 2, 1, 0));
    $gateway->method('validationContext')->willReturn(new InterventionValidationContext('site_setup', 'submitted', 'site-1', 'member-1'));
    $gateway->method('equipmentDrafts')->willReturn([]);

    $messages = array_column(new InterventionIssueFinder($gateway)->find('intervention-1'), 'message');

    self::assertContains('2 required work item(s) are incomplete.', $messages);
    self::assertContains('1 work item(s) were skipped and require review.', $messages);
  }

  #[Test]
  public function itFlagsEquipmentDraftsMissingFacilityOrSerialNumber(): void
  {
    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('summary')->willReturn(new InterventionResourceSummary(1, 1, 1));
    $gateway->method('workItemSummary')->willReturn(new InterventionWorkItemSummary(2, 0, 0, 0));
    $gateway->method('validationContext')->willReturn(new InterventionValidationContext('site_setup', 'submitted', 'site-1', 'member-1'));
    $gateway->method('equipmentDrafts')->willReturn([
      new InterventionEquipmentDraft('equipment-1', null, null),
      new InterventionEquipmentDraft('equipment-2', 'facility-1', ''),
      new InterventionEquipmentDraft('equipment-3', 'facility-1', 'SN-1000'),
    ]);

    $equipmentIssues = array_values(array_filter(
      new InterventionIssueFinder($gateway)->find('intervention-1'),
      static fn ($issue): bool => 'equipment' === $issue->resourceType,
    ));

    self::assertCount(3, $equipmentIssues);

    $facilityBlockers = array_values(array_filter(
      $equipmentIssues,
      static fn ($issue): bool => 'blocker' === $issue->severity,
    ));
    self::assertCount(1, $facilityBlockers);
    self::assertSame('equipment-1', $facilityBlockers[0]->resourceId);
    self::assertSame('facility', $facilityBlockers[0]->field);
    self::assertSame('Equipment must be assigned to a facility.', $facilityBlockers[0]->message);

    $serialRecommendations = array_values(array_filter(
      $equipmentIssues,
      static fn ($issue): bool => 'recommendation' === $issue->severity,
    ));
    self::assertCount(2, $serialRecommendations);
    self::assertSame(['equipment-1', 'equipment-2'], array_column($serialRecommendations, 'resourceId'));
    self::assertSame('serialNumber', $serialRecommendations[0]->field);
  }

  #[Test]
  public function itDefaultsToSiteSetupPolicyWhenValidationContextIsMissing(): void
  {
    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('summary')->willReturn(new InterventionResourceSummary(0, 0, 0));
    $gateway->method('workItemSummary')->willReturn(new InterventionWorkItemSummary(1, 0, 0, 0));
    $gateway->method('validationContext')->willReturn(null);
    $gateway->method('equipmentDrafts')->willReturn([]);

    $messages = array_column(new InterventionIssueFinder($gateway)->find('intervention-1'), 'message');

    self::assertContains('At least one facility is required.', $messages);
    self::assertContains('No equipment has been inventoried yet.', $messages);
    self::assertNotContains('No explicit work item has been prepared yet.', $messages);
  }

  #[Test]
  public function itUsesPreloadedSnapshotsWithoutQueryingGateway(): void
  {
    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('equipmentDrafts')->willReturn([]);

    $issues = new InterventionIssueFinder($gateway)->find(
      'intervention-1',
      new InterventionResourceSummary(2, 5, 3),
      new InterventionWorkItemSummary(0, 0, 0, 0),
      new InterventionValidationContext('inventory', 'draft', null, null),
    );

    $blocker = array_values(array_filter(
      $issues,
      static fn ($issue): bool => 'No explicit work item has been prepared yet.' === $issue->message,
    ));

    self::assertCount(1, $blocker);
    self::assertSame('blocker', $blocker[0]->severity);
  }
}
