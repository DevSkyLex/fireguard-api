<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Idempotency\ResponseProjection;

use Facility\Application\Port\Outbound\Internal\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment\{AddFacilityAttachmentCommand, AddFacilityAttachmentResult};
use Facility\Application\UseCase\Command\Facility\ArchiveFacility\ArchiveFacilityCommand;
use Facility\Application\UseCase\Command\Facility\DuplicateFacilitySubtree\{DuplicateFacilitySubtreeCommand, DuplicateFacilitySubtreeResult};
use Facility\Application\UseCase\Command\Facility\MoveFacility\MoveFacilityCommand;
use Facility\Application\UseCase\Command\Facility\PatchCanonicalFacility\PatchCanonicalFacilityCommand;
use Facility\Application\UseCase\Command\Facility\RestoreFacility\RestoreFacilityCommand;
use Facility\Application\UseCase\Command\Facility\SetFacilityPlanGeometry\SetFacilityPlanGeometryCommand;
use Facility\Application\UseCase\Command\Facility\UpdateFacility\UpdateFacilityCommand;
use Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\GetFacilityMediaContextQuery;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityQuery;
use Facility\Domain\ValueObject\FacilityId;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyResponseProjectionPort;

use function in_array;

/**
 * Adapter FacilityHttpIdempotencyResponseProjectionAdapter.
 *
 * Builds owner-local detail and attachment queries before the primary commit, including the created clone identifier.
 *
 * @category Adapter
 */
final readonly class FacilityHttpIdempotencyResponseProjectionAdapter implements HttpIdempotencyResponseProjectionPort
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @param FacilityRepositoryPort $facilities the owning transaction's facility scope
   */
  public function __construct(private FacilityRepositoryPort $facilities)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method supports.
   *
   * @param string $operation the explicit owner HTTP mutation
   *
   * @return bool whether its processor reads a response after the command
   */
  public function supports(string $operation): bool
  {
    return in_array($operation, ['facility_update', 'facility_archive', 'facility_restore', 'facility_move', 'facility_plan_geometry_set', 'facility_duplicate_subtree', 'canonical_facility_patch', '_api_/facilities/{facilityId}/attachments_post'], true);
  }

  /**
   * {@inheritdoc}
   */
  public function queries(string $operation, CommandMessage $command, ResultMessage $result): array
  {
    if (!$this->supports($operation)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if ($command instanceof PatchCanonicalFacilityCommand) {
      $facility = $this->facilities->findById(FacilityId::fromString($command->facilityId)) ?? throw new IdempotencyDeniedException('result_unavailable');

      return [
        new GetFacilityMediaContextQuery($command->userId ?? throw new IdempotencyDeniedException('result_unavailable'), facilityId: $command->facilityId),
        new GetFacilityQuery((string) $facility->organizationId(), $command->facilityId),
      ];
    }
    if ($command instanceof AddFacilityAttachmentCommand && $result instanceof AddFacilityAttachmentResult) {
      return [new GetFacilityMediaContextQuery($command->userId ?? throw new IdempotencyDeniedException('result_unavailable'), attachmentId: $result->attachmentId, write: true)];
    }
    if ($command instanceof DuplicateFacilitySubtreeCommand && $result instanceof DuplicateFacilitySubtreeResult) {
      return [new GetFacilityQuery($command->organizationId, $result->facilityId)];
    }
    if ($command instanceof UpdateFacilityCommand || $command instanceof ArchiveFacilityCommand || $command instanceof RestoreFacilityCommand || $command instanceof MoveFacilityCommand || $command instanceof SetFacilityPlanGeometryCommand) {
      return [new GetFacilityQuery($command->organizationId, $command->facilityId)];
    }

    throw new IdempotencyDeniedException('result_unavailable');
  }
  // #endregion
}
