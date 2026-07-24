<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Presentation\Api\Factory;

use Compliance\Application\Contract\FacilityTreeNode;
use Compliance\Domain\ValueObject\ComplianceStatus;
use Compliance\Presentation\Api\Factory\FacilityTreeOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityTreeOutputFactoryTest.
 *
 * Covers that nesting is preserved recursively and that the enum verdict is
 * serialized to its string value.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityTreeOutputFactory::class)]
final class FacilityTreeOutputFactoryTest extends TestCase
{
  #[Test]
  public function testFromTreeSerializesNestedChildrenRecursively(): void
  {
    $factory = new FacilityTreeOutputFactory();

    $floor = new FacilityTreeNode(
      id: 'floor-1',
      name: 'Floor 1',
      type: 'floor',
      parentFacilityId: 'building-1',
      equipmentCount: 2,
      status: ComplianceStatus::AT_RISK,
      complianceRate: 50.0,
      children: [],
    );
    $building = new FacilityTreeNode(
      id: 'building-1',
      name: 'Building A',
      type: 'building',
      parentFacilityId: 'site-1',
      equipmentCount: 2,
      status: ComplianceStatus::AT_RISK,
      complianceRate: 50.0,
      children: [$floor],
    );
    $site = new FacilityTreeNode(
      id: 'site-1',
      name: 'Site A',
      type: 'site',
      parentFacilityId: null,
      equipmentCount: 2,
      status: ComplianceStatus::AT_RISK,
      complianceRate: 50.0,
      children: [$building],
    );

    $output = $factory->fromTree(organizationId: 'org-1', generatedAt: '2026-01-01T00:00:00+00:00', tree: [$site]);

    self::assertSame('org-1', $output->organizationId);
    self::assertSame('2026-01-01T00:00:00+00:00', $output->generatedAt);
    self::assertCount(1, $output->nodes);

    // The top level is precisely shaped by the DTO; only the recursive
    // `children` levels degrade to `mixed`, so only those need narrowing.
    $site = $output->nodes[0];
    self::assertSame('site-1', $site['id']);
    self::assertSame('at_risk', $site['status']);
    self::assertSame(50.0, $site['complianceRate']);
    self::assertCount(1, $site['children']);

    $building = $site['children'][0];
    self::assertIsArray($building);
    self::assertSame('building-1', $building['id']);
    self::assertIsArray($building['children']);
    self::assertCount(1, $building['children']);

    $floor = $building['children'][0];
    self::assertIsArray($floor);
    self::assertSame('floor-1', $floor['id']);
    self::assertSame([], $floor['children']);
  }

  #[Test]
  public function testFromTreeReturnsEmptyNodesForAnEmptyTree(): void
  {
    $factory = new FacilityTreeOutputFactory();

    $output = $factory->fromTree(organizationId: 'org-1', generatedAt: '2026-01-01T00:00:00+00:00', tree: []);

    self::assertSame([], $output->nodes);
  }
}
