<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Attachment\ResolveInterventionMedia;

use Intervention\Application\Contract\Attachment\InterventionAttachmentView;
use Intervention\Application\Port\Outbound\InterventionMediaLookupPort;
use Intervention\Application\UseCase\Query\Attachment\ResolveInterventionMedia\{ResolveInterventionMediaHandler, ResolveInterventionMediaQuery};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionAttachmentNotFoundException, InterventionConflictException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResolveInterventionMediaHandlerTest extends TestCase
{
  #[Test]
  public function returnsTheAuthorizedStoredProjection(): void
  {
    $view = $this->view();
    $media = $this->createStub(InterventionMediaLookupPort::class);
    $media->method('attachment')->willReturn($view);
    $media->method('organizationId')->willReturn('organization');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with('actor', 'organization', 'organization.interventions.read')->willReturn(OrganizationAccessDecision::GRANTED);
    $result = new ResolveInterventionMediaHandler($media, $authorization)(new ResolveInterventionMediaQuery(userId: 'actor', attachmentId: 'attachment'));
    self::assertSame($view, $result->attachment);
    self::assertSame('organization', $result->organizationId);
  }

  #[Test]
  public function hidesAnOutOfScopeAttachment(): void
  {
    $media = $this->createStub(InterventionMediaLookupPort::class);
    $media->method('attachment')->willReturn($this->view());
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);
    $this->expectException(InterventionAttachmentNotFoundException::class);
    $this->expectExceptionMessage('Attachment not found.');
    new ResolveInterventionMediaHandler($media, $authorization)(new ResolveInterventionMediaQuery(userId: 'actor', attachmentId: 'attachment'));
  }

  #[Test]
  public function deniesAMemberWithoutReadPermission(): void
  {
    $media = $this->createStub(InterventionMediaLookupPort::class);
    $media->method('attachment')->willReturn($this->view());
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);
    $this->expectException(InterventionAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.interventions.read permission.');
    new ResolveInterventionMediaHandler($media, $authorization)(new ResolveInterventionMediaQuery(userId: 'actor', attachmentId: 'attachment'));
  }

  #[Test]
  public function rejectsAnAbsentAttachmentBeforePermissionChecks(): void
  {
    $media = $this->createStub(InterventionMediaLookupPort::class);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('resolveAccess');
    $this->expectException(InterventionAttachmentNotFoundException::class);
    new ResolveInterventionMediaHandler($media, $authorization)(new ResolveInterventionMediaQuery(userId: 'actor', attachmentId: 'missing'));
  }

  #[Test]
  public function checksReplayReadPermissionBeforeParentIdentityConflict(): void
  {
    $media = $this->createStub(InterventionMediaLookupPort::class);
    $media->method('organizationId')->willReturn('organization');
    $media->method('attachment')->willReturn($this->view());
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);
    $this->expectException(InterventionAccessDeniedException::class);
    new ResolveInterventionMediaHandler($media, $authorization)(new ResolveInterventionMediaQuery(userId: 'actor', attachmentId: 'attachment', interventionId: 'different', replay: true));
  }

  #[Test]
  public function refusesAReplayIdentityFromAnotherParent(): void
  {
    $media = $this->createStub(InterventionMediaLookupPort::class);
    $media->method('organizationId')->willReturn('organization');
    $media->method('attachment')->willReturn($this->view());
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $this->expectException(InterventionConflictException::class);
    new ResolveInterventionMediaHandler($media, $authorization)(new ResolveInterventionMediaQuery(userId: 'actor', attachmentId: 'attachment', interventionId: 'different', replay: true));
  }

  #[Test]
  public function leavesAFreshClientIdentityToTheMutationHandler(): void
  {
    $media = $this->createStub(InterventionMediaLookupPort::class);
    $media->method('organizationId')->willReturn('organization');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);
    $result = new ResolveInterventionMediaHandler($media, $authorization)(new ResolveInterventionMediaQuery(userId: 'actor', attachmentId: 'fresh', interventionId: 'parent', replay: true));
    self::assertNull($result->attachment);
  }

  private function view(): InterventionAttachmentView
  {
    return new InterventionAttachmentView('attachment', 'organization', 'intervention', 'file.pdf', 'application/pdf', 4, null, 3, '2026-01-01T00:00:00+00:00', null, 'file');
  }
}
