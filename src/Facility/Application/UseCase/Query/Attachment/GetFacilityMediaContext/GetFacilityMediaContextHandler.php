<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext;

use Facility\Application\Contract\Attachment\FacilityAttachmentView;
use Facility\Application\Port\Outbound\Internal\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilitySpatialValidityResolver;
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityAttachmentNotFoundException, FacilityNotFoundException};
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Handler GetFacilityMediaContextHandler.
 *
 * Keeps scope checks and spatial enrichment in the owning application.
 *
 * @category Handler
 */
final readonly class GetFacilityMediaContextHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct.
   */
  public function __construct(
    private FacilityRepositoryPort $facilities,
    private FacilityAttachmentRepositoryPort $attachments,
    private OrganizationAuthorizationPort $authorization,
    private FacilitySpatialValidityResolver $spatial,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   */
  public function __invoke(GetFacilityMediaContextQuery $query): GetFacilityMediaContextResult
  {
    $attachment = null;

    try {
      if (null !== $query->attachmentId) {
        $attachment = $this->attachments->findById(FacilityAttachmentId::fromString($query->attachmentId));
        if (null === $attachment) {
          throw new FacilityAttachmentNotFoundException('Attachment not found.');
        }
      }
      $id = null === $attachment ? FacilityId::fromString($query->facilityId ?? '') : $attachment->facilityId();
      $facility = $this->facilities->findById($id);
    } catch (InvalidValueException) {
      throw null === $query->attachmentId
        ? new FacilityNotFoundException('Facility not found.')
        : new FacilityAttachmentNotFoundException('Attachment not found.');
    }

    if (null === $facility) {
      throw null === $query->attachmentId
        ? new FacilityNotFoundException('Facility not found.')
        : new FacilityAttachmentNotFoundException('Attachment not found.');
    }
    $organizationId = (string) $facility->organizationId();
    $permission = $query->write ? 'organization.facilities.write' : 'organization.facilities.read';
    $decision = $this->authorization->resolveAccess($query->userId, $organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw null === $query->attachmentId
        ? new FacilityNotFoundException('Facility not found.')
        : new FacilityAttachmentNotFoundException('Attachment not found.');
    }
    if (!$decision->isGranted()) {
      throw new FacilityAccessDeniedException('Missing ' . $permission . ' permission.');
    }
    if (null === $attachment) {
      return new GetFacilityMediaContextResult($organizationId, (string) $id);
    }

    $context = $this->spatial->context($organizationId, [(string) $id], [(string) $attachment->id()]);
    $buildingId = $context->plans[(string) $attachment->id()]['calibrationBuildingId'] ?? null;

    return new GetFacilityMediaContextResult($organizationId, (string) $id, new FacilityAttachmentView(
      id: (string) $attachment->id(),
      facilityId: (string) $id,
      fileName: $attachment->file()->fileName,
      mimeType: $attachment->file()->mimeType,
      size: $attachment->file()->size,
      label: $attachment->label(),
      uploadedAt: $attachment->file()->uploadedAt->format('c'),
      kind: $attachment->kind()->value,
      isPrimaryPlan: $attachment->isPrimaryPlan(),
      imageWidth: $attachment->imageWidth(),
      imageHeight: $attachment->imageHeight(),
      calibration: $attachment->calibration()?->toArray(),
      calibrationBuildingId: $buildingId,
      calibrationIssue: $this->spatial->calibrationIssue($context, (string) $id, $buildingId, null !== $attachment->calibration()),
      revision: $attachment->revision(),
    ));
  }
  // #endregion
}
