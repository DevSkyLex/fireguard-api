<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Attachment\ListInterventionAttachments;

use Intervention\Application\Port\Outbound\{InterventionAttachmentRepositoryPort, InterventionResourceGatewayPort};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListInterventionAttachmentsHandler.
 *
 * Enforces the flat `organization.interventions.read` permission — the
 * authoritative check for this read; the Presentation provider performs no
 * authorization decision of its own for the collection route.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionAttachmentsHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private InterventionResourceGatewayPort $resources,
    private OrganizationAuthorizationPort $authorization,
    private InterventionAttachmentRepositoryPort $attachmentRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(ListInterventionAttachmentsQuery $query): ListInterventionAttachmentsResult
  {
    $context = $this->resources->interventionAssignmentContext($query->interventionId);

    if (null === $context) {
      throw InterventionNotFoundException::withId($query->interventionId);
    }

    if (!$this->authorization->hasPermission($query->userId, $context->organizationId, 'organization.interventions.read')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    $attachments = $this->attachmentRepository->findByInterventionId($query->interventionId);

    $result = [];
    foreach ($attachments as $attachment) {
      $result[] = [
        'id' => (string) $attachment->id(),
        'fileName' => $attachment->fileName(),
        'mimeType' => $attachment->mimeType(),
        'size' => $attachment->size(),
        'label' => $attachment->label(),
        'uploadedAt' => $attachment->uploadedAt()->format('c'),
      ];
    }

    return new ListInterventionAttachmentsResult($result);
  }
  // #endregion
}
