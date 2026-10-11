<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia;

use Inspection\Application\Port\Outbound\InspectionMediaLookupPort;
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionAttachmentNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/** Enforces target existence, organization scope and media read/write authorization.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveInspectionMediaHandler implements QueryHandler
{
  public function __construct(private InspectionMediaLookupPort $media, private OrganizationAuthorizationPort $authorization)
  {
  }

  public function __invoke(ResolveInspectionMediaQuery $query): ResolveInspectionMediaResult
  {
    $attachment = null === $query->attachmentId ? null : $this->media->attachment($query->attachmentId);
    $inspectionId = $query->inspectionId ?? $attachment?->inspectionId;
    $organizationId = null;
    $message = null !== $query->attachmentId ? 'Attachment not found.' : 'Inspection not found.';
    if (null !== $query->nonConformityId) {
      $scope = $this->media->nonConformityScope($query->nonConformityId);
      $inspectionId = $scope['inspectionId'] ?? null;
      $organizationId = $scope['organizationId'] ?? null;
      $message = 'Non-conformity not found.';
    } elseif (null !== $inspectionId) {
      $organizationId = null === $attachment ? $this->media->organizationId($inspectionId) : $attachment->organizationId;
    }
    if (null === $inspectionId || null === $organizationId || (null !== $query->attachmentId && null === $attachment)) {
      throw new InspectionAttachmentNotFoundException($message);
    }
    $permission = $query->write ? 'organization.inspection.write' : 'organization.inspection.read';
    $decision = $this->authorization->resolveAccess($query->userId, $organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw new InspectionAttachmentNotFoundException($query->deletion ? 'Non-conformity not found.' : $message);
    }
    if (!$decision->isGranted()) {
      throw new InspectionAccessDeniedException('Missing ' . $permission . ' permission.');
    }

    return new ResolveInspectionMediaResult($organizationId, $inspectionId, $attachment);
  }
}
