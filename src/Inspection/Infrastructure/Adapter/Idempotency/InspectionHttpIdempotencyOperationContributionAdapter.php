<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\{HttpIdempotencyOperation, HttpIdempotencyQueryProjection};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class InspectionHttpIdempotencyOperationContributionAdapter
 *
 * Declares every Inspection HTTP mutation, its owning main transaction and immutable result schema.
 * Root media owners retain their final commit; original responses use saved application projections.
 *
 * @category Adapter
 */
final readonly class InspectionHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<HttpIdempotencyOperation> explicit owner mutations
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(name: 'inspection_export', database: 'main', transactionMode: 'bus_owner', primaryCommand: \Inspection\Application\UseCase\Command\Export\ExportInspectionsDocument\ExportInspectionsDocumentCommand::class, resultClass: \Inspection\Application\Contract\Export\InspectionExportDocument::class, statusCode: 200, requiredKey: true),
      new HttpIdempotencyOperation(name: 'inspection_export_non_conformities', database: 'main', transactionMode: 'bus_owner', primaryCommand: \Inspection\Application\UseCase\Command\Export\ExportNonConformitiesDocument\ExportNonConformitiesDocumentCommand::class, resultClass: \Inspection\Application\Contract\Export\InspectionExportDocument::class, statusCode: 200, requiredKey: true),
      new HttpIdempotencyOperation(name: 'inspection_export_report', database: 'main', transactionMode: 'bus_owner', primaryCommand: \Inspection\Application\UseCase\Command\Export\ExportInspectionReportDocument\ExportInspectionReportDocumentCommand::class, resultClass: \Inspection\Application\Contract\Export\InspectionExportDocument::class, statusCode: 200, requiredKey: true, resultIdentifierProperty: 'resourceId', resourceKind: 'inspection'),
      new HttpIdempotencyOperation(name: 'inspection_export_non_conformities_report', database: 'main', transactionMode: 'bus_owner', primaryCommand: \Inspection\Application\UseCase\Command\Export\ExportNonConformitiesReportDocument\ExportNonConformitiesReportDocumentCommand::class, resultClass: \Inspection\Application\Contract\Export\InspectionExportDocument::class, statusCode: 200, requiredKey: true),

      new HttpIdempotencyOperation(
        name: '_api_/inspections_post',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Inspection\CreateInspection\CreateInspectionCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Inspection\CreateInspection\CreateInspectionResult::class,
        contractClasses: [\User\Application\Contract\User\UserView::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'inspectionId',
        resourceKind: 'inspection',
      ),
      new HttpIdempotencyOperation(
        name: 'canonical_inspection_put',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Inspection\CreateInspection\CreateInspectionCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Inspection\CreateInspection\CreateInspectionResult::class,
        contractClasses: [\User\Application\Contract\User\UserView::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'inspectionId',
        resourceKind: 'inspection',
      ),
      new HttpIdempotencyOperation(
        name: 'canonical_inspection_patch',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Inspection\PatchCanonicalInspection\PatchCanonicalInspectionCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Inspection\PatchCanonicalInspection\PatchCanonicalInspectionResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'inspection',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Inspection\Application\UseCase\Query\Inspection\GetCanonicalInspection\GetCanonicalInspectionQuery::class, \Inspection\Application\UseCase\Query\Inspection\GetCanonicalInspection\GetCanonicalInspectionResult::class, [\Inspection\Application\Contract\Inspection\CanonicalInspectionView::class], captureBeforePrimary: true, refreshBeforeCommit: true),
          new HttpIdempotencyQueryProjection(\Inspection\Application\UseCase\Query\Inspection\ReadCanonicalInspection\ReadCanonicalInspectionQuery::class, \Inspection\Application\UseCase\Query\Inspection\ReadCanonicalInspection\ReadCanonicalInspectionResult::class, [\Inspection\Application\Contract\Inspection\CanonicalInspectionReadView::class], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'canonical_inspection_delete',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Inspection\DeleteCanonicalInspection\DeleteCanonicalInspectionCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Inspection\DeleteCanonicalInspection\DeleteCanonicalInspectionResult::class,
        statusCode: 204,
        requiredKey: true,
        resourceKind: 'inspection',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Inspection\Application\UseCase\Query\Inspection\GetCanonicalInspection\GetCanonicalInspectionQuery::class, \Inspection\Application\UseCase\Query\Inspection\GetCanonicalInspection\GetCanonicalInspectionResult::class, [\Inspection\Application\Contract\Inspection\CanonicalInspectionView::class], captureBeforePrimary: true, refreshBeforeCommit: false),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'inspection_create_checklist',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Checklist\CreateChecklist\CreateChecklistCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Checklist\CreateChecklist\CreateChecklistResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'checklistId',
        resourceKind: 'checklist',
      ),
      new HttpIdempotencyOperation(
        name: 'inspection_archive_checklist',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Checklist\ArchiveChecklist\ArchiveChecklistCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Checklist\ArchiveChecklist\ArchiveChecklistResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'checklist',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Inspection\Application\UseCase\Query\Checklist\GetChecklist\GetChecklistQuery::class, \Inspection\Application\UseCase\Query\Checklist\GetChecklist\GetChecklistResult::class, [\Inspection\Application\UseCase\Query\Checklist\GetChecklist\ChecklistItemResult::class], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'inspection_update_checklist',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Checklist\UpdateChecklist\UpdateChecklistCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Checklist\UpdateChecklist\UpdateChecklistResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'checklist',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Inspection\Application\UseCase\Query\Checklist\GetChecklist\GetChecklistQuery::class, \Inspection\Application\UseCase\Query\Checklist\GetChecklist\GetChecklistResult::class, [\Inspection\Application\UseCase\Query\Checklist\GetChecklist\ChecklistItemResult::class], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'add_inspection_attachment',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment\AddInspectionAttachmentCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment\AddInspectionAttachmentResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'attachmentId',
        resourceKind: 'inspection_attachment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia\ResolveInspectionMediaQuery::class, \Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia\ResolveInspectionMediaResult::class, [\Inspection\Application\Contract\Attachment\InspectionAttachmentView::class], captureBeforePrimary: true, refreshBeforeCommit: false),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'add_non_conformity_attachment',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment\AddInspectionAttachmentCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment\AddInspectionAttachmentResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'attachmentId',
        resourceKind: 'inspection_attachment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia\ResolveInspectionMediaQuery::class, \Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia\ResolveInspectionMediaResult::class, [\Inspection\Application\Contract\Attachment\InspectionAttachmentView::class], captureBeforePrimary: true, refreshBeforeCommit: false),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'delete_inspection_attachment',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment\DeleteInspectionAttachmentCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment\DeleteInspectionAttachmentResult::class,
        statusCode: 204,
        requiredKey: true,
        resourceKind: 'inspection_attachment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia\ResolveInspectionMediaQuery::class, \Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia\ResolveInspectionMediaResult::class, [\Inspection\Application\Contract\Attachment\InspectionAttachmentView::class], captureBeforePrimary: true, refreshBeforeCommit: false),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'inspection_create',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Inspection\CreateInspection\CreateInspectionCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Inspection\CreateInspection\CreateInspectionResult::class,
        contractClasses: [\User\Application\Contract\User\UserView::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'inspectionId',
        resourceKind: 'inspection',
      ),
      new HttpIdempotencyOperation(
        name: 'inspection_edit',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Inspection\EditInspection\EditInspectionCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Inspection\EditInspection\EditInspectionResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'inspection',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionQuery::class, \Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult::class, [\User\Application\Contract\User\UserView::class], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'inspection_submit',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Inspection\SubmitInspection\SubmitInspectionCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Inspection\SubmitInspection\SubmitInspectionResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'inspection',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionQuery::class, \Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult::class, [\User\Application\Contract\User\UserView::class], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'inspection_close',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Inspection\CloseInspection\CloseInspectionCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Inspection\CloseInspection\CloseInspectionResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'inspection',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionQuery::class, \Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult::class, [\User\Application\Contract\User\UserView::class], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'inspection_cancel',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Inspection\CancelInspection\CancelInspectionCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Inspection\CancelInspection\CancelInspectionResult::class,
        statusCode: 204,
        requiredKey: true,
        resourceKind: 'inspection',
      ),
      new HttpIdempotencyOperation(
        name: '_api_/inspection-responses_post',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Response\CreateInspectionResponse\CreateInspectionResponseCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Response\CreateInspectionResponse\CreateInspectionResponseResult::class,
        contractClasses: [\Inspection\Application\Contract\Response\InspectionResponseView::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'view.id',
        resourceKind: 'inspection_response',
        freshAuthorizationQueries: [\Inspection\Application\UseCase\Query\Response\GetInspectionResponse\GetInspectionResponseQuery::class],
      ),
      new HttpIdempotencyOperation(
        name: 'inspection_response_put',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Response\CreateInspectionResponse\CreateInspectionResponseCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Response\CreateInspectionResponse\CreateInspectionResponseResult::class,
        contractClasses: [\Inspection\Application\Contract\Response\InspectionResponseView::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'view.id',
        resourceKind: 'inspection_response',
        freshAuthorizationQueries: [\Inspection\Application\UseCase\Query\Response\GetInspectionResponse\GetInspectionResponseQuery::class],
      ),
      new HttpIdempotencyOperation(
        name: '_api_/inspection-responses/{id}_patch',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Response\UpdateInspectionResponse\UpdateInspectionResponseCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Response\UpdateInspectionResponse\UpdateInspectionResponseResult::class,
        contractClasses: [\Inspection\Application\Contract\Response\InspectionResponseView::class],
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'inspection_response',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Inspection\Application\UseCase\Query\Response\GetInspectionResponse\GetInspectionResponseQuery::class, \Inspection\Application\UseCase\Query\Response\GetInspectionResponse\GetInspectionResponseResult::class, [\Inspection\Application\Contract\Response\InspectionResponseView::class], captureBeforePrimary: true, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: '_api_/inspection-responses/{id}_delete',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\Response\DeleteInspectionResponse\DeleteInspectionResponseCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\Response\DeleteInspectionResponse\DeleteInspectionResponseResult::class,
        statusCode: 204,
        requiredKey: true,
        resourceKind: 'inspection_response',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Inspection\Application\UseCase\Query\Response\GetInspectionResponse\GetInspectionResponseQuery::class, \Inspection\Application\UseCase\Query\Response\GetInspectionResponse\GetInspectionResponseResult::class, [\Inspection\Application\Contract\Response\InspectionResponseView::class], captureBeforePrimary: true, refreshBeforeCommit: false),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'inspection_add_non_conformity',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\NonConformity\AddNonConformity\AddNonConformityCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\NonConformity\AddNonConformity\AddNonConformityResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'nonConformityId',
        resourceKind: 'non_conformity',
      ),
      new HttpIdempotencyOperation(
        name: 'inspection_update_non_conformity_status',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inspection\Application\UseCase\Command\NonConformity\UpdateNonConformityStatus\UpdateNonConformityStatusCommand::class,
        resultClass: \Inspection\Application\UseCase\Command\NonConformity\UpdateNonConformityStatus\UpdateNonConformityStatusResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'non_conformity',
        freshAuthorizationQueries: [\Inspection\Application\UseCase\Query\NonConformity\GetNonConformity\GetNonConformityQuery::class],
      ),
    ];
  }
  // #endregion
}
