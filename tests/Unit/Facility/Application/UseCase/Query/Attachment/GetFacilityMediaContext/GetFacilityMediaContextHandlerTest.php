<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext;

use Facility\Application\Contract\Spatial\FacilitySpatialContext;
use Facility\Application\Port\Outbound\FacilitySpatialReadPort;
use Facility\Application\Port\Outbound\Internal\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilitySpatialValidityResolver;
use Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\{GetFacilityMediaContextHandler, GetFacilityMediaContextQuery};
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityAttachmentNotFoundException, FacilityNotFoundException};
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Throwable;

/** Authorization and typed attachment snapshot regressions for the HTTP-independent read capability. */
final class GetFacilityMediaContextHandlerTest extends TestCase
{
  private const string ORGANIZATION = 'dc130000-0000-4000-8000-000000000001';

  private const string FACILITY = 'dc130000-0000-4000-8000-000000000002';

  private const string ATTACHMENT = 'dc130000-0000-4000-8000-000000000003';

  #[Test]
  public function aForeignOrganizationIsIndistinguishableFromAMissingAttachment(): void
  {
    $this->expectException(FacilityAttachmentNotFoundException::class);
    $this->expectExceptionMessage('Attachment not found.');
    $this->handler(OrganizationAccessDecision::OUTSIDE_SCOPE)(new GetFacilityMediaContextQuery('reader', attachmentId: self::ATTACHMENT));
  }

  #[Test]
  public function anInScopeReaderWithoutEntitlementReceivesTheReadDenial(): void
  {
    $this->expectException(FacilityAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.facilities.read permission.');
    $this->handler(OrganizationAccessDecision::MISSING_PERMISSION)(new GetFacilityMediaContextQuery('reader', attachmentId: self::ATTACHMENT));
  }

  #[Test]
  public function aWriteContextChecksTheWritePermissionBeforeMultipartWork(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with('writer', self::ORGANIZATION, 'organization.facilities.write')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);
    $this->expectException(FacilityAccessDeniedException::class);
    $this->handler(OrganizationAccessDecision::MISSING_PERMISSION, $authorization)(new GetFacilityMediaContextQuery('writer', facilityId: self::FACILITY, write: true));
  }

  #[Test]
  public function aMalformedAttachmentIdentifierReturnsTheStableNotFound(): void
  {
    $this->expectException(FacilityAttachmentNotFoundException::class);
    $this->expectExceptionMessage('Attachment not found.');
    $this->handler(OrganizationAccessDecision::GRANTED)(new GetFacilityMediaContextQuery('reader', attachmentId: 'invalid'));
  }

  #[Test]
  public function authorizedReadsReturnACompleteScalarSnapshot(): void
  {
    $result = $this->handler(OrganizationAccessDecision::GRANTED)(new GetFacilityMediaContextQuery('reader', attachmentId: self::ATTACHMENT));
    self::assertSame(self::ORGANIZATION, $result->organizationId);
    self::assertSame(self::FACILITY, $result->facilityId);
    self::assertNotNull($result->attachment);
    self::assertSame(self::ATTACHMENT, $result->attachment->id);
    self::assertSame('document.pdf', $result->attachment->fileName);
    self::assertSame('document', $result->attachment->kind);
    self::assertSame(1, $result->attachment->revision);
    self::assertNull($result->attachment->calibration);
  }

  /**
   * Method aMissingParentKeepsTheRequestedContextNotFoundBeforeAuthorization
   *
   * @param class-string<Throwable> $exceptionClass expected requested context refusal
   */
  #[Test]
  #[DataProvider('missingParents')]
  public function aMissingParentKeepsTheRequestedContextNotFoundBeforeAuthorization(?string $attachmentId, string $exceptionClass, string $message): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('resolveAccess');
    $this->expectException($exceptionClass);
    $this->expectExceptionMessage($message);
    $this->handler(OrganizationAccessDecision::GRANTED, $authorization, facilityExists: false)(new GetFacilityMediaContextQuery('reader', facilityId: self::FACILITY, attachmentId: $attachmentId));
  }

  /**
   * Method missingParents
   *
   * @return iterable<string, array{?string, class-string<Throwable>, string}>
   */
  public static function missingParents(): iterable
  {
    yield 'facility context' => [null, FacilityNotFoundException::class, 'Facility not found.'];
    yield 'attachment context' => [self::ATTACHMENT, FacilityAttachmentNotFoundException::class, 'Attachment not found.'];
  }

  private function handler(OrganizationAccessDecision $decision, ?OrganizationAuthorizationPort $authorization = null, bool $facilityExists = true): GetFacilityMediaContextHandler
  {
    $facilities = $this->createStub(FacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($facilityExists ? Facility::create(FacilityId::fromString(self::FACILITY), FacilityOrganizationId::fromString(self::ORGANIZATION), FacilityType::SITE, new FacilityName('Site')) : null);
    $attachments = $this->createStub(FacilityAttachmentRepositoryPort::class);
    $attachments->method('findById')->willReturn(FacilityAttachment::create(FacilityAttachmentId::fromString(self::ATTACHMENT), FacilityId::fromString(self::FACILITY), 'document.pdf', 'facility/document.pdf', 'application/pdf', 4));
    if (null === $authorization) {
      $authorization = $this->createStub(OrganizationAuthorizationPort::class);
      $authorization->method('resolveAccess')->willReturn($decision);
    }
    $spatial = $this->createStub(FacilitySpatialReadPort::class);
    $spatial->method('readContext')->willReturn(new FacilitySpatialContext());

    return new GetFacilityMediaContextHandler($facilities, $attachments, $authorization, new FacilitySpatialValidityResolver($spatial));
  }
}
