<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Infrastructure\Adapter\Idempotency\ResponseProjection;

use DateTimeImmutable;
use Facility\Application\Port\Outbound\Internal\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment\{AddFacilityAttachmentCommand, AddFacilityAttachmentResult};
use Facility\Application\UseCase\Command\Facility\DuplicateFacilitySubtree\{DuplicateFacilitySubtreeCommand, DuplicateFacilitySubtreeResult};
use Facility\Application\UseCase\Command\Facility\PatchCanonicalFacility\PatchCanonicalFacilityCommand;
use Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\GetFacilityMediaContextQuery;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityQuery;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use Facility\Infrastructure\Adapter\Idempotency\ResponseProjection\FacilityHttpIdempotencyResponseProjectionAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\VoidResult;

/**
 * Test FacilityHttpIdempotencyResponseProjectionAdapterTest.
 *
 * Uses the actual created resource identifiers and preserves the canonical two-query organization scope.
 *
 * @category Unit Tests
 */
#[CoversClass(FacilityHttpIdempotencyResponseProjectionAdapter::class)]
final class FacilityHttpIdempotencyResponseProjectionAdapterTest extends TestCase
{
  #[Test]
  public function itConstructsBothCanonicalReadsWithinTheOwnerScope(): void
  {
    $id = '550e8400-e29b-41d4-a716-446655440031';
    $organization = '550e8400-e29b-41d4-a716-446655440032';
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->with(FacilityId::fromString($id))->willReturn(Facility::create(FacilityId::fromString($id), FacilityOrganizationId::fromString($organization), FacilityType::SITE, new FacilityName('Saved site')));
    $factory = new FacilityHttpIdempotencyResponseProjectionAdapter($repository);

    self::assertEquals([new GetFacilityMediaContextQuery('actor', facilityId: $id), new GetFacilityQuery($organization, $id)], $factory->queries('canonical_facility_patch', new PatchCanonicalFacilityCommand($id, 2, userId: 'actor'), new VoidResult()));
  }

  #[Test]
  public function itUsesTheSavedAttachmentAndCloneIdentifiersForPostonlyReads(): void
  {
    $factory = new FacilityHttpIdempotencyResponseProjectionAdapter($this->createStub(FacilityRepositoryPort::class));
    $at = new DateTimeImmutable('2030-01-01');
    self::assertEquals([new GetFacilityMediaContextQuery('actor', attachmentId: 'created-attachment', write: true)], $factory->queries('_api_/facilities/{facilityId}/attachments_post', new AddFacilityAttachmentCommand('organization', 'facility', 'photo.jpg', 'bytes', 'image/jpeg', 5, userId: 'actor'), new AddFacilityAttachmentResult('created-attachment', 'facility', 'photo.jpg', 'image/jpeg', 5, null, $at)));
    self::assertEquals([new GetFacilityQuery('organization', 'created-clone')], $factory->queries('facility_duplicate_subtree', new DuplicateFacilitySubtreeCommand('organization', 'original-facility'), new DuplicateFacilitySubtreeResult('created-clone', 'organization', null, 'site', 'Copied site', null, 'active', null, [], $at, $at, null, null, 1)));
  }
}
