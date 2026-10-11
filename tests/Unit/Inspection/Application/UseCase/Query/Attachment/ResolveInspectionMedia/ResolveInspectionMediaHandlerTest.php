<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia;

use Inspection\Application\Contract\Attachment\InspectionAttachmentView;
use Inspection\Application\Port\Outbound\InspectionMediaLookupPort;
use Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia\{ResolveInspectionMediaHandler, ResolveInspectionMediaQuery};
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionAttachmentNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResolveInspectionMediaHandlerTest extends TestCase
{
  #[Test]
  public function returnsTheAuthorizedStoredProjection(): void
  {
    $view = $this->view();
    $media = $this->createStub(InspectionMediaLookupPort::class);
    $media->method('attachment')->willReturn($view);
    $media->method('organizationId')->willReturn('organization');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with('actor', 'organization', 'organization.inspection.read')->willReturn(OrganizationAccessDecision::GRANTED);
    $result = new ResolveInspectionMediaHandler($media, $authorization)(new ResolveInspectionMediaQuery(userId: 'actor', attachmentId: 'attachment'));
    self::assertSame($view, $result->attachment);
    self::assertSame('organization', $result->organizationId);
  }

  #[Test]
  public function hidesAnOutOfScopeAttachment(): void
  {
    $media = $this->createStub(InspectionMediaLookupPort::class);
    $media->method('attachment')->willReturn($this->view());
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);
    $this->expectException(InspectionAttachmentNotFoundException::class);
    $this->expectExceptionMessage('Attachment not found.');
    new ResolveInspectionMediaHandler($media, $authorization)(new ResolveInspectionMediaQuery(userId: 'actor', attachmentId: 'attachment'));
  }

  #[Test]
  public function deniesAMemberWithoutReadPermission(): void
  {
    $media = $this->createStub(InspectionMediaLookupPort::class);
    $media->method('attachment')->willReturn($this->view());
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);
    $this->expectException(InspectionAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.inspection.read permission.');
    new ResolveInspectionMediaHandler($media, $authorization)(new ResolveInspectionMediaQuery(userId: 'actor', attachmentId: 'attachment'));
  }

  #[Test]
  public function rejectsAnAbsentAttachmentBeforePermissionChecks(): void
  {
    $media = $this->createStub(InspectionMediaLookupPort::class);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('resolveAccess');
    $this->expectException(InspectionAttachmentNotFoundException::class);
    new ResolveInspectionMediaHandler($media, $authorization)(new ResolveInspectionMediaQuery(userId: 'actor', attachmentId: 'missing'));
  }

  #[Test]
  public function resolvesTheNonConformityParentAndWritePermission(): void
  {
    $media = $this->createStub(InspectionMediaLookupPort::class);
    $media->method('nonConformityScope')->willReturn(['inspectionId' => 'inspection', 'organizationId' => 'organization']);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with('actor', 'organization', 'organization.inspection.write')->willReturn(OrganizationAccessDecision::GRANTED);
    $result = new ResolveInspectionMediaHandler($media, $authorization)(new ResolveInspectionMediaQuery(userId: 'actor', nonConformityId: 'non-conformity', write: true));
    self::assertSame('inspection', $result->inspectionId);
  }

  #[Test]
  public function preservesTheDeletionOutsideScopeMessage(): void
  {
    $media = $this->createStub(InspectionMediaLookupPort::class);
    $media->method('attachment')->willReturn($this->view());
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);
    $this->expectException(InspectionAttachmentNotFoundException::class);
    $this->expectExceptionMessage('Non-conformity not found.');
    new ResolveInspectionMediaHandler($media, $authorization)(new ResolveInspectionMediaQuery(userId: 'actor', attachmentId: 'attachment', write: true, deletion: true));
  }

  private function view(): InspectionAttachmentView
  {
    return new InspectionAttachmentView('attachment', 'organization', 'inspection', 'file.pdf', 'application/pdf', 4, null, 3, '2026-01-01T00:00:00+00:00', null);
  }
}
