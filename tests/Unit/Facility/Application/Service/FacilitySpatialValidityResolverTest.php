<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\Service;

use Facility\Application\Contract\Spatial\FacilitySpatialContext;
use Facility\Application\Port\Outbound\FacilitySpatialReadPort;
use Facility\Application\Service\FacilitySpatialValidityResolver;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use const INF;
use const NAN;

/**
 * Test FacilitySpatialValidityResolverTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilitySpatialValidityResolver::class)]
final class FacilitySpatialValidityResolverTest extends TestCase
{
  private const string PLAN_A = '550e8400-e29b-41d4-a716-446655440101';

  private const string PLAN_B = '550e8400-e29b-41d4-a716-446655440102';

  #[Test]
  public function testWholeMovedSubtreeRetainsReferencesButLosesTheirUsability(): void
  {
    $context = $this->context();
    $resolver = new FacilitySpatialValidityResolver($this->createStub(FacilitySpatialReadPort::class));
    $geometry = ['attachmentId' => self::PLAN_A, 'points' => [[0.1, 0.1], [0.8, 0.1], [0.8, 0.8]]];
    foreach (['zone', 'room'] as $id) {
      self::assertSame('outside_ancestry', $resolver->geometryIssue($context, $id, $geometry, self::PLAN_B));
      self::assertSame('outside_ancestry', $resolver->positionIssue($context, $id, ['attachmentId' => self::PLAN_A, 'x' => 0.4, 'y' => 0.6], self::PLAN_B));
      self::assertTrue($resolver->geometryIsAuthorized($context, $geometry));
    }
    self::assertSame('building_changed', $resolver->calibrationIssue($context, 'floor-b', 'building-a', true));
    self::assertSame('unverified_frame', $resolver->calibrationIssue($context, 'floor-b', null, true));
    self::assertNull($resolver->calibrationIssue($context, 'floor-b', 'building-b', true));
    self::assertSame(self::PLAN_A, $geometry['attachmentId']);
    self::assertSame([0.1, 0.1], $geometry['points'][0]);
  }

  #[Test]
  public function testForeignOrDeletedPlanProducesNoReadableSourceGeometry(): void
  {
    $resolver = new FacilitySpatialValidityResolver($this->createStub(FacilitySpatialReadPort::class));
    $geometry = ['attachmentId' => '550e8400-e29b-41d4-a716-446655440999', 'points' => [[0.1, 0.1], [0.8, 0.1], [0.8, 0.8]]];
    self::assertSame('plan_unavailable', $resolver->geometryIssue($this->context(), 'room', $geometry));
    self::assertFalse($resolver->geometryIsAuthorized($this->context(), $geometry));
    self::assertSame('other_plan', $resolver->positionIssue($this->context(), 'room', ['attachmentId' => $geometry['attachmentId'], 'x' => 0.3, 'y' => 0.4], self::PLAN_B));
  }

  #[Test]
  public function testOtherPlanAndInvalidPolygonAreDistinctFromHierarchyMismatch(): void
  {
    $context = $this->context();
    $resolver = new FacilitySpatialValidityResolver($this->createStub(FacilitySpatialReadPort::class));
    $geometry = ['attachmentId' => self::PLAN_B, 'points' => [[0.1, 0.1], [0.8, 0.1], [0.8, 0.8]]];
    self::assertNull($resolver->geometryIssue($context, 'room', $geometry, self::PLAN_B));
    self::assertSame('other_plan', $resolver->geometryIssue($context, 'room', $geometry, self::PLAN_A));
    $geometry['points'] = [[0.1, 0.1], [0.5, 0.5], [0.9, 0.9]];
    self::assertSame('invalid_geometry', $resolver->geometryIssue($context, 'room', $geometry, self::PLAN_B));
    self::assertFalse($resolver->geometryIsAuthorized($context, $geometry));
    self::assertSame('unplaced', $resolver->positionIssue($context, 'room', null, self::PLAN_B));
    self::assertSame('invalid_position', $resolver->positionIssue($context, 'room', null, self::PLAN_B, true));
  }

  #[Test]
  public function testContextIsOneBatchAndLegacyCycleStopsWithoutInventingABuilding(): void
  {
    $port = $this->createMock(FacilitySpatialReadPort::class);
    $context = new FacilitySpatialContext(['a' => ['parentId' => 'b', 'type' => 'zone', 'recordStatus' => 'published'], 'b' => ['parentId' => 'a', 'type' => 'area', 'recordStatus' => 'published']]);
    $port->expects(self::once())->method('readContext')->with('organization', ['a', 'b'], [self::PLAN_A])->willReturn($context);
    $resolver = new FacilitySpatialValidityResolver($port);
    self::assertSame($context, $resolver->context('organization', ['a', 'b'], [self::PLAN_A]));
    self::assertSame(['a', 'b'], $context->ancestors('a'));
    self::assertNull($context->nearest('a', 'building'));
    self::assertSame('unverified_frame', $resolver->calibrationIssue($context, 'a', 'building-a', true));
  }

  /**
   * Method testUnreadablePlanTakesPrecedenceOverInvalidPolygonData.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnreadablePlanTakesPrecedenceOverInvalidPolygonData(): void
  {
    $resolver = new FacilitySpatialValidityResolver($this->createStub(FacilitySpatialReadPort::class));
    $geometry = ['attachmentId' => '550e8400-e29b-41d4-a716-446655440999', 'points' => []];
    self::assertSame('plan_unavailable', $resolver->geometryIssue($this->context(), 'room', $geometry, self::PLAN_B));
    self::assertFalse($resolver->geometryIsAuthorized($this->context(), $geometry));
    self::assertNull($resolver->geometryIssue($this->context(), 'room', null, self::PLAN_B));
  }

  /**
   * Method testInvalidPolygonPrecedesAncestryAndRenderedPlanDiagnostics.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testInvalidPolygonPrecedesAncestryAndRenderedPlanDiagnostics(): void
  {
    $resolver = new FacilitySpatialValidityResolver($this->createStub(FacilitySpatialReadPort::class));
    $geometry = ['attachmentId' => self::PLAN_A, 'points' => [[0.1, 0.1], [0.5, 0.5], [0.9, 0.9]]];
    self::assertSame('invalid_geometry', $resolver->geometryIssue($this->context(), 'room', $geometry, self::PLAN_B));
  }

  /**
   * Method testCoordinateFailuresPrecedeMissingPlanAndAncestryDiagnostics.
   *
   * @access public
   *
   * @param float $x the persisted horizontal coordinate
   * @param float $y the persisted vertical coordinate
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidPositions')]
  public function testCoordinateFailuresPrecedeMissingPlanAndAncestryDiagnostics(float $x, float $y): void
  {
    $resolver = new FacilitySpatialValidityResolver($this->createStub(FacilitySpatialReadPort::class));
    $position = ['attachmentId' => self::PLAN_A, 'x' => $x, 'y' => $y];
    self::assertSame('invalid_position', $resolver->positionIssue($this->context(), 'room', $position, null));
    self::assertSame('invalid_position', $resolver->positionIssue($this->context(), 'room', $position, self::PLAN_B));
  }

  /**
   * Method testMissingFrameAndPlacementRemainDistinctAndCoordinatesIncludeTheirBoundaries.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testMissingFrameAndPlacementRemainDistinctAndCoordinatesIncludeTheirBoundaries(): void
  {
    $resolver = new FacilitySpatialValidityResolver($this->createStub(FacilitySpatialReadPort::class));
    self::assertSame('invalid_position', $resolver->positionIssue($this->context(), 'room', null, null, true));
    self::assertSame('missing_plan', $resolver->positionIssue($this->context(), 'room', null, null));
    self::assertSame('unplaced', $resolver->positionIssue($this->context(), 'room', null, self::PLAN_B));
    self::assertNull($resolver->positionIssue($this->context(), 'room', ['attachmentId' => self::PLAN_B, 'x' => 0.0, 'y' => 1.0], self::PLAN_B));
    self::assertSame('other_plan', $resolver->positionIssue($this->context(), 'room', ['attachmentId' => self::PLAN_B, 'x' => 0.0, 'y' => 1.0], self::PLAN_A));
  }

  /**
   * Method invalidPositions.
   *
   * Includes both non-finite values and each side of the normalized coordinate range.
   *
   * @access public
   *
   * @return iterable<string, array{float, float}> persisted positions that must be omitted
   */
  public static function invalidPositions(): iterable
  {
    return [
      'non-finite x' => [NAN, 0.5],
      'non-finite y' => [0.5, INF],
      'negative x' => [-0.1, 0.5],
      'x exceeds the frame' => [1.1, 0.5],
      'negative y' => [0.5, -0.1],
      'y exceeds the frame' => [0.5, 1.1],
    ];
  }

  private function context(): FacilitySpatialContext
  {
    return new FacilitySpatialContext([
      'building-a' => ['parentId' => null, 'type' => 'building', 'recordStatus' => 'published'],
      'building-b' => ['parentId' => null, 'type' => 'building', 'recordStatus' => 'published'],
      'floor-a' => ['parentId' => 'building-a', 'type' => 'floor', 'recordStatus' => 'published'],
      'floor-b' => ['parentId' => 'building-b', 'type' => 'floor', 'recordStatus' => 'published'],
      'zone' => ['parentId' => 'floor-b', 'type' => 'zone', 'recordStatus' => 'published'],
      'room' => ['parentId' => 'zone', 'type' => 'area', 'recordStatus' => 'published'],
    ], [
      self::PLAN_A => ['facilityId' => 'floor-a', 'primary' => true, 'calibrationBuildingId' => 'building-a'],
      self::PLAN_B => ['facilityId' => 'floor-b', 'primary' => true, 'calibrationBuildingId' => 'building-b'],
    ]);
  }
}
