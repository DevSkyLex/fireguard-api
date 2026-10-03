<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\Service;

use Facility\Application\Contract\Hierarchy\FacilityHierarchyNode;
use Facility\Application\Port\Outbound\FacilityHierarchySnapshotPort;
use Facility\Application\Service\{FacilityHierarchyPublicationContext, FacilityLifecycleReferenceGuard};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(FacilityLifecycleReferenceGuard::class)]
final class FacilityLifecycleReferenceGuardTest extends TestCase
{
  /**
   * @return iterable<string, array{string, ?string, ?string, bool}>
   */
  public static function references(): iterable
  {
    yield 'published resource to published facility' => ['published', null, null, true];
    yield 'published resource to draft facility' => ['draft', null, null, false];
    yield 'draft resource to its own draft' => ['draft', 'same-intervention', 'same-intervention', true];
    yield 'draft resource to another draft' => ['draft', 'other-intervention', 'same-intervention', false];
    yield 'draft resource to published facility' => ['published', 'same-intervention', null, true];
  }

  #[Test]
  #[DataProvider('references')]
  public function itRespectsThePublicationScope(string $publication, ?string $resourceIntervention, ?string $facilityIntervention, bool $allowed): void
  {
    $snapshot = $this->createStub(FacilityHierarchySnapshotPort::class);
    $snapshot->method('load')->willReturn(['facility' => new FacilityHierarchyNode('facility', 'site', null, publicationState: $publication, interventionId: $facilityIntervention)]);
    $guard = new FacilityLifecycleReferenceGuard($snapshot, new FacilityHierarchyPublicationContext());
    if (!$allowed) {
      $this->expectException(InvalidArgumentException::class);
    }
    $guard->assertReference('organization', 'facility', $resourceIntervention);
    self::assertTrue($allowed);
  }

  #[Test]
  public function itUsesTheValidatedFutureStateDuringAtomicPublicationAndClearsIt(): void
  {
    $snapshot = $this->createStub(FacilityHierarchySnapshotPort::class);
    $snapshot->method('load')->willReturn(['facility' => new FacilityHierarchyNode('facility', 'site', null, publicationState: 'draft', interventionId: 'same-intervention')]);
    $context = new FacilityHierarchyPublicationContext();
    $context->enter('organization', [new FacilityHierarchyNode('facility', 'site', null)]);
    $guard = new FacilityLifecycleReferenceGuard($snapshot, $context);
    $guard->assertReference('organization', 'facility');
    $context->leave();
    $this->expectException(InvalidArgumentException::class);
    $guard->assertReference('organization', 'facility');
  }

  #[Test]
  public function itCannotUseAnotherOrganizationsPublicationContext(): void
  {
    $snapshot = $this->createStub(FacilityHierarchySnapshotPort::class);
    $snapshot->method('load')->willReturn([]);
    $context = new FacilityHierarchyPublicationContext();
    $context->enter('foreign-organization', [new FacilityHierarchyNode('facility', 'site', null)]);
    $this->expectException(InvalidArgumentException::class);
    new FacilityLifecycleReferenceGuard($snapshot, $context)->assertReference('organization', 'facility');
  }

  /**
   * @return iterable<string, array{string, ?string, ?string, ?string, bool}>
   */
  public static function retainedReferences(): iterable
  {
    yield 'published archived facility' => ['published', null, null, null, true];
    yield 'draft resource to published archived facility' => ['published', 'same', null, null, true];
    yield 'published resource to draft' => ['draft', null, 'same', null, false];
    yield 'draft resource to its own draft' => ['draft', 'same', 'same', null, true];
    yield 'draft resource to foreign draft' => ['draft', 'same', 'other', null, false];
    yield 'same atomic publication draft' => ['draft', null, 'same', 'same', true];
    yield 'foreign atomic publication draft' => ['draft', null, 'other', 'same', false];
    yield 'draft without owner' => ['draft', 'same', null, 'same', false];
  }

  #[Test]
  #[DataProvider('retainedReferences')]
  public function itRetainsHistoricalReferencesWithinThePublicationScope(string $publication, ?string $resourceIntervention, ?string $facilityIntervention, ?string $publishingIntervention, bool $allowed): void
  {
    $snapshot = $this->createStub(FacilityHierarchySnapshotPort::class);
    $snapshot->method('load')->willReturn(['facility' => new FacilityHierarchyNode('facility', 'site', null, 'archived', $publication, $facilityIntervention)]);
    $guard = new FacilityLifecycleReferenceGuard($snapshot, new FacilityHierarchyPublicationContext());
    if (!$allowed) {
      $this->expectException(InvalidArgumentException::class);
    }
    $guard->assertRetainedReference('organization', 'facility', $resourceIntervention, $publishingIntervention);
    self::assertTrue($allowed);
  }

  #[Test]
  public function itRejectsNewAssignmentsToArchivedFacilitiesInThePublicationContext(): void
  {
    $snapshot = $this->createStub(FacilityHierarchySnapshotPort::class);
    $snapshot->method('load')->willReturn(['facility' => new FacilityHierarchyNode('facility', 'site', null)]);
    $context = new FacilityHierarchyPublicationContext();
    $context->enter('organization', [new FacilityHierarchyNode('facility', 'site', null, 'archived')]);
    $guard = new FacilityLifecycleReferenceGuard($snapshot, $context);
    $guard->assertRetainedReference('organization', 'facility');
    $this->expectException(InvalidArgumentException::class);
    $guard->assertReference('organization', 'facility');
  }

  #[Test]
  public function itRejectsMissingOrForeignHistoricalFacilities(): void
  {
    $snapshot = $this->createMock(FacilityHierarchySnapshotPort::class);
    $snapshot->expects(self::once())->method('load')->with('organization')->willReturn([]);
    $context = new FacilityHierarchyPublicationContext();
    $context->enter('foreign-organization', [new FacilityHierarchyNode('facility', 'site', null, 'archived')]);
    $this->expectException(InvalidArgumentException::class);
    new FacilityLifecycleReferenceGuard($snapshot, $context)->assertRetainedReference('organization', 'facility');
  }
}
