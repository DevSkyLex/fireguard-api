<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\{HttpIdempotencyOperation, HttpIdempotencyQueryProjection};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class EquipmentHttpIdempotencyOperationContributionAdapter
 *
 * Declares every Equipment HTTP mutation, its owning main transaction and immutable result schema.
 * Root media owners retain their final commit; original responses use saved application projections.
 *
 * @category Adapter
 */
final readonly class EquipmentHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
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
      new HttpIdempotencyOperation(
        name: 'equipment_export',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Export\GenerateEquipmentExport\GenerateEquipmentExportCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Export\GenerateEquipmentExport\GenerateEquipmentExportResult::class,
        requiredKey: true,
        resourceKind: 'equipment_export',
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_export_report',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Export\GenerateEquipmentExport\GenerateEquipmentExportCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Export\GenerateEquipmentExport\GenerateEquipmentExportResult::class,
        requiredKey: true,
        resourceKind: 'equipment',
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_export_labels',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Export\GenerateEquipmentExport\GenerateEquipmentExportCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Export\GenerateEquipmentExport\GenerateEquipmentExportResult::class,
        requiredKey: true,
        resourceKind: 'equipment_export',
      ),
      new HttpIdempotencyOperation(
        name: '_api_/equipment_post',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\CreateEquipment\CreateEquipmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\CreateEquipment\CreateEquipmentResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'equipmentId',
        resourceKind: 'equipment',
      ),
      new HttpIdempotencyOperation(
        name: 'canonical_equipment_put',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\CreateEquipment\CreateEquipmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\CreateEquipment\CreateEquipmentResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'equipmentId',
        resourceKind: 'equipment',
      ),
      new HttpIdempotencyOperation(
        name: 'canonical_equipment_patch',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\PatchCanonicalEquipment\PatchCanonicalEquipmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\PatchCanonicalEquipment\PatchCanonicalEquipmentResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'equipment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment\GetCanonicalEquipmentQuery::class, \Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment\GetCanonicalEquipmentResult::class, [\Equipment\Application\Contract\Equipment\CanonicalEquipmentView::class], captureBeforePrimary: true, refreshBeforeCommit: true),
          new HttpIdempotencyQueryProjection(\Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailQuery::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailResult::class, [\Equipment\Application\Contract\Equipment\CanonicalEquipmentView::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult::class], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'canonical_equipment_delete',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\DeleteCanonicalEquipment\DeleteCanonicalEquipmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\DeleteCanonicalEquipment\DeleteCanonicalEquipmentResult::class,
        statusCode: 204,
        requiredKey: true,
        resourceKind: 'equipment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment\GetCanonicalEquipmentQuery::class, \Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment\GetCanonicalEquipmentResult::class, [\Equipment\Application\Contract\Equipment\CanonicalEquipmentView::class], captureBeforePrimary: true, refreshBeforeCommit: false),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_add_attachment',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\AddAttachment\AddAttachmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\AddAttachment\AddAttachmentResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'attachmentId',
        resourceKind: 'equipment_attachment',
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_delete_attachment',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\DeleteAttachment\DeleteAttachmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\DeleteAttachment\DeleteAttachmentResult::class,
        statusCode: 204,
        requiredKey: true,
        resourceKind: 'equipment_attachment',
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_replace',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\ReplaceEquipment\ReplaceEquipmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\ReplaceEquipment\ReplaceEquipmentResult::class,
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'successorEquipmentId',
        resourceKind: 'equipment',
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_create',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\CreateEquipment\CreateEquipmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\CreateEquipment\CreateEquipmentResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'equipmentId',
        resourceKind: 'equipment',
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_update',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\UpdateEquipment\UpdateEquipmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\UpdateEquipment\UpdateEquipmentResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'equipment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailQuery::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailResult::class, [\Equipment\Application\Contract\Equipment\CanonicalEquipmentView::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult::class], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_assign_to_facility',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\AssignToFacility\AssignToFacilityCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\AssignToFacility\AssignToFacilityResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'equipment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailQuery::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailResult::class, [\Equipment\Application\Contract\Equipment\CanonicalEquipmentView::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult::class], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_unassign_from_facility',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\UnassignFromFacility\UnassignFromFacilityCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\UnassignFromFacility\UnassignFromFacilityResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'equipment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailQuery::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailResult::class, [\Equipment\Application\Contract\Equipment\CanonicalEquipmentView::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult::class], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_commission',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\CommissionEquipment\CommissionEquipmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\CommissionEquipment\CommissionEquipmentResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'equipment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailQuery::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailResult::class, [\Equipment\Application\Contract\Equipment\CanonicalEquipmentView::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult::class], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_maintenance',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\PutUnderMaintenance\PutUnderMaintenanceCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\PutUnderMaintenance\PutUnderMaintenanceResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'equipment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailQuery::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailResult::class, [\Equipment\Application\Contract\Equipment\CanonicalEquipmentView::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult::class], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_plan_position_set',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\SetEquipmentPlanPosition\SetEquipmentPlanPositionCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\SetEquipmentPlanPosition\SetEquipmentPlanPositionResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'equipment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailQuery::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailResult::class, [\Equipment\Application\Contract\Equipment\CanonicalEquipmentView::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult::class], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_decommission',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment\DecommissionEquipmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment\DecommissionEquipmentResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'equipment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailQuery::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailResult::class, [\Equipment\Application\Contract\Equipment\CanonicalEquipmentView::class, \Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult::class], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_add_tag',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\AddTagToEquipment\AddTagToEquipmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\AddTagToEquipment\AddTagToEquipmentResult::class,
        statusCode: 201,
        requiredKey: true,
        resourceKind: 'equipment',
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_remove_tag',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\RemoveTagFromEquipment\RemoveTagFromEquipmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\RemoveTagFromEquipment\RemoveTagFromEquipmentResult::class,
        statusCode: 204,
        requiredKey: true,
        resourceKind: 'equipment',
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_type_catalog_create',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\EquipmentTypeCatalog\CreateEquipmentType\CreateEquipmentTypeCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\EquipmentTypeCatalog\CreateEquipmentType\CreateEquipmentTypeResult::class,
        contractClasses: [\Equipment\Application\Contract\EquipmentTypeCatalog\EquipmentTypeDescriptor::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'type.value',
        resourceKind: 'equipment_type',
      ),
      new HttpIdempotencyOperation(
        name: 'equipment_type_catalog_patch',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\EquipmentTypeCatalog\PatchEquipmentType\PatchEquipmentTypeCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\EquipmentTypeCatalog\PatchEquipmentType\PatchEquipmentTypeResult::class,
        contractClasses: [\Equipment\Application\Contract\EquipmentTypeCatalog\EquipmentTypeDescriptor::class],
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'equipment_type',
      ),
      new HttpIdempotencyOperation(
        name: '_api_/media_post',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\AddAttachment\AddAttachmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\AddAttachment\AddAttachmentResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'attachmentId',
        resourceKind: 'equipment_attachment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Equipment\Application\UseCase\Query\Media\PrepareEquipmentMediaUpload\PrepareEquipmentMediaUploadQuery::class, \Equipment\Application\UseCase\Query\Media\PrepareEquipmentMediaUpload\PrepareEquipmentMediaUploadResult::class, [\Equipment\Application\Contract\Media\EquipmentMediaView::class], captureBeforePrimary: true, refreshBeforeCommit: false),
          new HttpIdempotencyQueryProjection(\Equipment\Application\UseCase\Query\Media\GetEquipmentMedia\GetEquipmentMediaQuery::class, \Equipment\Application\UseCase\Query\Media\GetEquipmentMedia\GetEquipmentMediaResult::class, [\Equipment\Application\Contract\Media\EquipmentMediaView::class], captureBeforePrimary: true, refreshBeforeCommit: false),
        ],
      ),
      new HttpIdempotencyOperation(
        name: '_api_/media/{id}_delete',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Equipment\Application\UseCase\Command\Equipment\DeleteAttachment\DeleteAttachmentCommand::class,
        resultClass: \Equipment\Application\UseCase\Command\Equipment\DeleteAttachment\DeleteAttachmentResult::class,
        statusCode: 204,
        requiredKey: true,
        resourceKind: 'equipment_attachment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Equipment\Application\UseCase\Query\Media\PrepareEquipmentMediaUpload\PrepareEquipmentMediaUploadQuery::class, \Equipment\Application\UseCase\Query\Media\PrepareEquipmentMediaUpload\PrepareEquipmentMediaUploadResult::class, [\Equipment\Application\Contract\Media\EquipmentMediaView::class], captureBeforePrimary: true, refreshBeforeCommit: false),
          new HttpIdempotencyQueryProjection(\Equipment\Application\UseCase\Query\Media\GetEquipmentMedia\GetEquipmentMediaQuery::class, \Equipment\Application\UseCase\Query\Media\GetEquipmentMedia\GetEquipmentMediaResult::class, [\Equipment\Application\Contract\Media\EquipmentMediaView::class], captureBeforePrimary: true, refreshBeforeCommit: false),
        ],
      ),
    ];
  }
  // #endregion
}
