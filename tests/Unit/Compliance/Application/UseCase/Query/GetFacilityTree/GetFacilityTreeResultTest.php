<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\UseCase\Query\GetFacilityTree;

use Compliance\Application\Contract\FacilityTreeNode;
use Compliance\Application\UseCase\Query\GetFacilityTree\GetFacilityTreeResult;
use Compliance\Domain\ValueObject\ComplianceStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test GetFacilityTreeResultTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityTreeResult::class)]
final class GetFacilityTreeResultTest extends TestCase
{
  #[Test]
  public function testConstructorExposesItsProperties(): void
  {
    $node = new FacilityTreeNode(
      id: 'site-1',
      name: 'Site A',
      type: 'site',
      parentFacilityId: null,
      equipmentCount: 2,
      status: ComplianceStatus::COMPLIANT,
      complianceRate: 100.0,
      children: [],
    );

    $result = new GetFacilityTreeResult(
      generatedAt: '2026-07-24T10:00:00+00:00',
      tree: [$node],
    );

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame('2026-07-24T10:00:00+00:00', $result->generatedAt);
    self::assertSame([$node], $result->tree);
  }

  #[Test]
  public function testConstructorAcceptsAnEmptyTree(): void
  {
    $result = new GetFacilityTreeResult(generatedAt: '2026-07-24T10:00:00+00:00', tree: []);

    self::assertSame([], $result->tree);
  }
}
