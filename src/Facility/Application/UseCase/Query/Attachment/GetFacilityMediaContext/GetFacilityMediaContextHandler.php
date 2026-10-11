<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext;

use Facility\Application\Contract\Attachment\FacilityAttachmentView;
use Facility\Application\Port\Outbound\Internal\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilitySpatialValidityResolver;
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityAttachmentNotFoundException, FacilityNotFoundException};
use Facility\Domain\Model\Attachment\FacilityAttachment;
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
  // #region Properties
  /**
   * Constant ATTACHMENT_NOT_FOUND
   *
   * Keeps the attachment denial message stable across lookup and scope refusals.
   */
  private const string ATTACHMENT_NOT_FOUND = 'Attachment not found.';

  /**
   * Constant FACILITY_NOT_FOUND
   *
   * Keeps the facility denial message stable across lookup and scope refusals.
   */
  private const string FACILITY_NOT_FOUND = 'Facility not found.';
  // #endregion

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
    try {
      $attachment = $this->findAttachment($query->attachmentId);
      $id = null === $attachment ? FacilityId::fromString($query->facilityId ?? '') : $attachment->facilityId();
      $facility = $this->facilities->findById($id);
    } catch (InvalidValueException) {
      throw $this->notFound($query);
    }

    if (null === $facility) {
      throw $this->notFound($query);
    }
    $organizationId = (string) $facility->organizationId();
    $permission = $query->write ? 'organization.facilities.write' : 'organization.facilities.read';
    $decision = $this->authorization->resolveAccess($query->userId, $organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw $this->notFound($query);
    }
    if (!$decision->isGranted()) {
      throw new FacilityAccessDeniedException('Missing ' . $permission . ' permission.');
    }
    if (null === $attachment) {
      return new GetFacilityMediaContextResult($organizationId, (string) $id);
    }

    return new GetFacilityMediaContextResult($organizationId, (string) $id, $this->attachmentView($attachment, $organizationId, $id));
  }

  /**
   * Method findAttachment
   *
   * Resolves an attachment identity before parent lookup and authorization.
   *
   * @access private
   *
   * @param ?string $attachmentId optional attachment context identity
   *
   * @return ?FacilityAttachment existing attachment or a facility-only context
   */
  private function findAttachment(?string $attachmentId): ?FacilityAttachment
  {
    $attachment = null;
    if (null !== $attachmentId) {
      $attachment = $this->attachments->findById(FacilityAttachmentId::fromString($attachmentId));
      if (null === $attachment) {
        throw new FacilityAttachmentNotFoundException(self::ATTACHMENT_NOT_FOUND);
      }
    }

    return $attachment;
  }

  /**
   * Method notFound
   *
   * Uses the requested context to keep missing and inaccessible resources indistinguishable.
   *
   * @access private
   *
   * @param GetFacilityMediaContextQuery $query requested context
   *
   * @return FacilityNotFoundException|FacilityAttachmentNotFoundException stable context refusal
   */
  private function notFound(GetFacilityMediaContextQuery $query): FacilityNotFoundException|FacilityAttachmentNotFoundException
  {
    return null === $query->attachmentId
      ? new FacilityNotFoundException(self::FACILITY_NOT_FOUND)
      : new FacilityAttachmentNotFoundException(self::ATTACHMENT_NOT_FOUND);
  }

  /**
   * Method attachmentView
   *
   * Enriches an authorized attachment using the grouped spatial context.
   *
   * @access private
   *
   * @param FacilityAttachment $attachment authorized attachment
   * @param string $organizationId resolved owning organization
   * @param FacilityId $id resolved parent identity
   *
   * @return FacilityAttachmentView complete attachment snapshot
   */
  private function attachmentView(FacilityAttachment $attachment, string $organizationId, FacilityId $id): FacilityAttachmentView
  {
    $context = $this->spatial->context($organizationId, [(string) $id], [(string) $attachment->id()]);
    $buildingId = $context->plans[(string) $attachment->id()]['calibrationBuildingId'] ?? null;

    return new FacilityAttachmentView(
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
    );
  }
  // #endregion
}
