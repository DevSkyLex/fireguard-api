<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Attachment\ResolveInterventionMedia;

use Intervention\Application\Port\Outbound\InterventionMediaLookupPort;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionAttachmentNotFoundException, InterventionConflictException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/** Enforces target existence, organization scope and media read/write authorization.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveInterventionMediaHandler implements QueryHandler
{
  public function __construct(private InterventionMediaLookupPort $media, private OrganizationAuthorizationPort $authorization)
  {
  }

  public function __invoke(ResolveInterventionMediaQuery $query): ResolveInterventionMediaResult
  {
    $requestedOrganization = null;
    if (null !== $query->interventionId) {
      $requestedOrganization = $this->media->organizationId($query->interventionId);
      if (null === $requestedOrganization || $this->authorization->resolveAccess($query->userId, $requestedOrganization, 'organization.interventions.read')->isOutsideScope()) {
        throw new InterventionAttachmentNotFoundException('Attachment not found.');
      }
    }
    $attachment = null === $query->attachmentId ? null : $this->media->attachment($query->attachmentId);
    if (null === $attachment && !$query->replay) {
      throw new InterventionAttachmentNotFoundException('Attachment not found.');
    }
    if (null === $attachment) {
      return new ResolveInterventionMediaResult($requestedOrganization ?? '', $query->interventionId ?? '');
    }
    if (!$query->deletion) {
      $decision = $this->authorization->resolveAccess($query->userId, $attachment->organizationId, 'organization.interventions.read');
      if ($decision->isOutsideScope()) {
        throw new InterventionAttachmentNotFoundException('Attachment not found.');
      }
      if (!$decision->isGranted()) {
        throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
      }
    }
    if (null !== $query->interventionId && $attachment->interventionId !== $query->interventionId) {
      throw new InterventionConflictException('Attachment client UUID is already assigned to another intervention.');
    }

    return new ResolveInterventionMediaResult($attachment->organizationId, $attachment->interventionId, $attachment);
  }
}
