<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\Contract;

use Compliance\Application\Contract\FacilityTreeNode;
use Compliance\Domain\ValueObject\ComplianceStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityTreeNodeTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityTreeNode::class)]
final class FacilityTreeNodeTest extends TestCase
{
  #[Test]
  public function testConstructorExposesAllProperties(): void
  {
    $child = new FacilityTreeNode(
      id: 'child-1',
      name: 'Building A',
      type: 'building',
      parentFacilityId: 'site-1',
      equipmentCount: 4,
      status: ComplianceStatus::COMPLIANT,
      complianceRate: 100.0,
      children: [],
    );

    $root = new FacilityTreeNode(
      id: 'site-1',
      name: 'Site A',
      type: 'site',
      parentFacilityId: null,
      equipmentCount: 10,
      status: ComplianceStatus::AT_RISK,
      complianceRate: 87.5,
      children: [$child],
    );

    self::assertSame('site-1', $root->id);
    self::assertSame('Site A', $root->name);
    self::assertSame('site', $root->type);
    self::assertNull($root->parentFacilityId);
    self::assertSame(10, $root->equipmentCount);
    self::assertSame(ComplianceStatus::AT_RISK, $root->status);
    self::assertSame(87.5, $root->complianceRate);
    self::assertSame([$child], $root->children);
    self::assertSame('site-1', $root->children[0]->parentFacilityId);
  }

  #[Test]
  public function testConstructorAllowsANullComplianceRate(): void
  {
    $node = new FacilityTreeNode(
      id: 'zone-1',
      name: 'Zone Z',
      type: 'zone',
      parentFacilityId: 'building-1',
      equipmentCount: 0,
      status: ComplianceStatus::NOT_APPLICABLE,
      complianceRate: null,
      children: [],
    );

    self::assertNull($node->complianceRate);
    self::assertSame(ComplianceStatus::NOT_APPLICABLE, $node->status);
  }
}
