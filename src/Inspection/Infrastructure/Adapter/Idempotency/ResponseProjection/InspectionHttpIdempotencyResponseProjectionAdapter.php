<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Idempotency\ResponseProjection;

use Inspection\Application\UseCase\Command\Checklist\ArchiveChecklist\ArchiveChecklistCommand;
use Inspection\Application\UseCase\Command\Checklist\UpdateChecklist\UpdateChecklistCommand;
use Inspection\Application\UseCase\Command\Inspection\CloseInspection\CloseInspectionCommand;
use Inspection\Application\UseCase\Command\Inspection\EditInspection\EditInspectionCommand;
use Inspection\Application\UseCase\Command\Inspection\PatchCanonicalInspection\PatchCanonicalInspectionCommand;
use Inspection\Application\UseCase\Command\Inspection\SubmitInspection\SubmitInspectionCommand;
use Inspection\Application\UseCase\Query\Checklist\GetChecklist\GetChecklistQuery;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionQuery;
use Inspection\Application\UseCase\Query\Inspection\ReadCanonicalInspection\ReadCanonicalInspectionQuery;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyResponseProjectionPort;

use function in_array;

/**
 * Adapter InspectionHttpIdempotencyResponseProjectionAdapter.
 *
 * Constructs exact postwrite inspection and checklist reads for their owning transaction's receipt.
 *
 * @category Adapter
 */
final readonly class InspectionHttpIdempotencyResponseProjectionAdapter implements HttpIdempotencyResponseProjectionPort
{
  // #region Methods
  /**
   * Method supports.
   *
   * @param string $operation the explicit owner HTTP mutation
   *
   * @return bool whether its processor first reads its output after the command
   */
  public function supports(string $operation): bool
  {
    return in_array($operation, ['inspection_edit', 'inspection_submit', 'inspection_close', 'inspection_archive_checklist', 'inspection_update_checklist', 'canonical_inspection_patch'], true);
  }

  /**
   * {@inheritdoc}
   */
  public function queries(string $operation, CommandMessage $command, ResultMessage $result): array
  {
    if (!$this->supports($operation)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if ($command instanceof PatchCanonicalInspectionCommand) {
      return [new ReadCanonicalInspectionQuery($command->inspectionId)];
    }
    if ($command instanceof EditInspectionCommand || $command instanceof SubmitInspectionCommand || $command instanceof CloseInspectionCommand) {
      return [new GetInspectionQuery($command->organizationId, $command->inspectionId)];
    }
    if ($command instanceof ArchiveChecklistCommand || $command instanceof UpdateChecklistCommand) {
      return [new GetChecklistQuery($command->organizationId, $command->checklistId)];
    }

    throw new IdempotencyDeniedException('result_unavailable');
  }
  // #endregion
}
