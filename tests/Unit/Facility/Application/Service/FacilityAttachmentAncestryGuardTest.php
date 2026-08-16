<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\Service;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\Service\FacilityAttachmentAncestryGuard;
use Facility\Domain\Exception\FacilityAttachmentNotAncestorException;
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityAttachmentAncestryGuardTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityAttachmentAncestryGuard::class)]
final class FacilityAttachmentAncestryGuardTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440941';

  #[Test]
  public function testAssertBelongsToFacilityOrAncestorPassesWhenAttachmentBelongsToTheFacilityItself(): void
  {
    $facility = $this->facility('550e8400-e29b-41d4-a716-446655440001', self::ORGANIZATION_ID);
    $attachment = $this->attachment('550e8400-e29b-41d4-a716-446655440002', '550e8400-e29b-41d4-a716-446655440001');

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::never())->method('findById');

    $guard = new FacilityAttachmentAncestryGuard($facilityRepository);

    $guard->assertBelongsToFacilityOrAncestor($facility, $attachment, FacilityOrganizationId::fromString(self::ORGANIZATION_ID));

    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testAssertBelongsToFacilityOrAncestorPassesWhenAttachmentBelongsToAGrandparent(): void
  {
    $grandparentId = '550e8400-e29b-41d4-a716-446655440010';
    $parentId = '550e8400-e29b-41d4-a716-446655440011';
    $facilityId = '550e8400-e29b-41d4-a716-446655440012';

    $grandparent = $this->facility($grandparentId, self::ORGANIZATION_ID);
    $parent = $this->facility($parentId, self::ORGANIZATION_ID, $grandparentId);
    $facility = $this->facility($facilityId, self::ORGANIZATION_ID, $parentId);
    $attachment = $this->attachment('550e8400-e29b-41d4-a716-446655440002', $grandparentId);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::atLeastOnce())->method('findById')->willReturnCallback(
      static fn (FacilityId $id): ?Facility => match ((string) $id) {
        $parentId => $parent,
        $grandparentId => $grandparent,
        default => null,
      },
    );

    $guard = new FacilityAttachmentAncestryGuard($facilityRepository);

    $guard->assertBelongsToFacilityOrAncestor($facility, $attachment, FacilityOrganizationId::fromString(self::ORGANIZATION_ID));

    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testAssertBelongsToFacilityOrAncestorThrowsWhenAttachmentBelongsToADescendant(): void
  {
    $facilityId = '550e8400-e29b-41d4-a716-446655440012';
    $descendantId = '550e8400-e29b-41d4-a716-446655440013';

    $facility = $this->facility($facilityId, self::ORGANIZATION_ID);
    $attachment = $this->attachment('550e8400-e29b-41d4-a716-446655440002', $descendantId);

    $guard = new FacilityAttachmentAncestryGuard($this->createStub(FacilityRepositoryPort::class));

    $this->expectException(FacilityAttachmentNotAncestorException::class);

    $guard->assertBelongsToFacilityOrAncestor($facility, $attachment, FacilityOrganizationId::fromString(self::ORGANIZATION_ID));
  }

  #[Test]
  public function testAssertBelongsToFacilityOrAncestorThrowsWhenAncestorCrossesIntoAnotherOrganization(): void
  {
    $parentId = '550e8400-e29b-41d4-a716-446655440011';
    $facilityId = '550e8400-e29b-41d4-a716-446655440012';
    $beyondParentId = '550e8400-e29b-41d4-a716-446655440014';
    $otherOrganizationId = '550e8400-e29b-41d4-a716-446655449999';

    // $parent belongs to another organization — an unreachable state for a
    // legitimate hierarchy (Move/Create already refuse a cross-org parent),
    // but the walk must still stop there rather than trust the id alone:
    // the attachment belongs to a facility ABOVE $parent, which must never
    // be reached once the walk crosses an organization boundary.
    $parent = $this->facility($parentId, $otherOrganizationId, $beyondParentId);
    $facility = $this->facility($facilityId, self::ORGANIZATION_ID, $parentId);
    $attachment = $this->attachment('550e8400-e29b-41d4-a716-446655440002', $beyondParentId);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::once())->method('findById')->willReturn($parent);

    $guard = new FacilityAttachmentAncestryGuard($facilityRepository);

    $this->expectException(FacilityAttachmentNotAncestorException::class);

    $guard->assertBelongsToFacilityOrAncestor($facility, $attachment, FacilityOrganizationId::fromString(self::ORGANIZATION_ID));
  }

  /**
   * Method facility.
   *
   * @since 1.0.0
   */
  private function facility(string $id, string $organizationId, ?string $parentFacilityId = null): Facility
  {
    return Facility::create(
      id: FacilityId::fromString($id),
      organizationId: FacilityOrganizationId::fromString($organizationId),
      type: FacilityType::ZONE,
      name: new FacilityName('Test Zone'),
      parentFacilityId: null !== $parentFacilityId ? FacilityId::fromString($parentFacilityId) : null,
    );
  }

  /**
   * Method attachment.
   *
   * @since 1.0.0
   */
  private function attachment(string $id, string $facilityId): FacilityAttachment
  {
    return FacilityAttachment::create(
      id: FacilityAttachmentId::fromString($id),
      facilityId: FacilityId::fromString($facilityId),
      fileName: 'plan.png',
      storagePath: 'facility/' . $facilityId . '/attachments/' . $id . '_plan.png',
      mimeType: 'image/png',
      size: 1024,
      kind: AttachmentKind::FLOOR_PLAN,
    );
  }
}
