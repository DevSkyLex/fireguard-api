<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\{HttpIdempotencyOperation, HttpIdempotencyQueryProjection};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class FacilityHttpIdempotencyOperationContributionAdapter
 *
 * Declares every Facility HTTP mutation, its owning main transaction and immutable result schema.
 * Root media owners retain their final commit; original responses use saved application projections.
 *
 * @category Adapter
 */
final readonly class FacilityHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
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
        name: 'facility_export',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Export\GenerateFacilitiesExport\GenerateFacilitiesExportCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Export\GenerateFacilitiesExport\GenerateFacilitiesExportResult::class,
        requiredKey: true,
        resourceKind: 'facility_export',
      ),
      new HttpIdempotencyOperation(
        name: '_api_/facilities_post',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Facility\CreateFacility\CreateFacilityCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Facility\CreateFacility\CreateFacilityResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'facilityId',
        resourceKind: 'facility',
      ),
      new HttpIdempotencyOperation(
        name: 'canonical_facility_put',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Facility\CreateFacility\CreateFacilityCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Facility\CreateFacility\CreateFacilityResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'facilityId',
        resourceKind: 'facility',
      ),
      new HttpIdempotencyOperation(
        name: 'canonical_facility_patch',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Facility\PatchCanonicalFacility\PatchCanonicalFacilityCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Facility\PatchCanonicalFacility\PatchCanonicalFacilityResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'facility',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Facility\Application\UseCase\Query\Facility\GetCanonicalFacility\GetCanonicalFacilityQuery::class, \Facility\Application\UseCase\Query\Facility\GetCanonicalFacility\GetCanonicalFacilityResult::class, [\Facility\Application\Contract\Facility\CanonicalFacilityView::class], captureBeforePrimary: true, refreshBeforeCommit: true),
          new HttpIdempotencyQueryProjection(\Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\GetFacilityMediaContextQuery::class, \Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\GetFacilityMediaContextResult::class, [\Facility\Application\Contract\Attachment\FacilityAttachmentView::class], captureBeforePrimary: true, refreshBeforeCommit: true),
          new HttpIdempotencyQueryProjection(\Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityQuery::class, \Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult::class, [], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'canonical_facility_delete',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Facility\DeleteCanonicalFacility\DeleteCanonicalFacilityCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Facility\DeleteCanonicalFacility\DeleteCanonicalFacilityResult::class,
        statusCode: 204,
        requiredKey: true,
        resourceKind: 'facility',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Facility\Application\UseCase\Query\Facility\GetCanonicalFacility\GetCanonicalFacilityQuery::class, \Facility\Application\UseCase\Query\Facility\GetCanonicalFacility\GetCanonicalFacilityResult::class, [\Facility\Application\Contract\Facility\CanonicalFacilityView::class], captureBeforePrimary: true, refreshBeforeCommit: false),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'facility_attachment_set_calibration',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Attachment\SetFacilityAttachmentCalibration\SetFacilityAttachmentCalibrationCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Attachment\SetFacilityAttachmentCalibration\SetFacilityAttachmentCalibrationResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'facility_attachment',
      ),
      new HttpIdempotencyOperation(
        name: '_api_/facilities/{facilityId}/attachments_post',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment\AddFacilityAttachmentCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment\AddFacilityAttachmentResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'attachmentId',
        resourceKind: 'facility_attachment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\GetFacilityMediaContextQuery::class, \Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\GetFacilityMediaContextResult::class, [\Facility\Application\Contract\Attachment\FacilityAttachmentView::class], captureBeforePrimary: true, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: '_api_/facility-attachments/{id}_delete',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment\DeleteFacilityAttachmentCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment\DeleteFacilityAttachmentResult::class,
        statusCode: 204,
        requiredKey: true,
        resourceKind: 'facility_attachment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\GetFacilityMediaContextQuery::class, \Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\GetFacilityMediaContextResult::class, [\Facility\Application\Contract\Attachment\FacilityAttachmentView::class], captureBeforePrimary: true, refreshBeforeCommit: false),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'facility_attachment_set_primary',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Attachment\SetPrimaryFacilityAttachment\SetPrimaryFacilityAttachmentCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Attachment\SetPrimaryFacilityAttachment\SetPrimaryFacilityAttachmentResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'facility_attachment',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\GetFacilityMediaContextQuery::class, \Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\GetFacilityMediaContextResult::class, [\Facility\Application\Contract\Attachment\FacilityAttachmentView::class], captureBeforePrimary: true, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'facility_metadata_field_create',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\MetadataField\CreateMetadataField\CreateMetadataFieldCommand::class,
        resultClass: \Facility\Application\UseCase\Command\MetadataField\CreateMetadataField\CreateMetadataFieldResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'facility_metadata_field',
      ),
      new HttpIdempotencyOperation(
        name: 'facility_metadata_field_update',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\MetadataField\UpdateMetadataField\UpdateMetadataFieldCommand::class,
        resultClass: \Facility\Application\UseCase\Command\MetadataField\UpdateMetadataField\UpdateMetadataFieldResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'facility_metadata_field',
      ),
      new HttpIdempotencyOperation(
        name: 'facility_metadata_field_delete',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\MetadataField\DeleteMetadataField\DeleteMetadataFieldCommand::class,
        resultClass: \Facility\Application\UseCase\Command\MetadataField\DeleteMetadataField\DeleteMetadataFieldResult::class,
        statusCode: 204,
        requiredKey: true,
        resourceKind: 'facility_metadata_field',
      ),
      new HttpIdempotencyOperation(
        name: 'upload_facility_model',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Model\UploadFacilityModel\UploadFacilityModelCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Model\UploadFacilityModel\UploadFacilityModelResult::class,
        contractClasses: [\Facility\Application\Contract\Model\FacilityModelView::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'model.id',
        resourceKind: 'facility_model',
      ),
      new HttpIdempotencyOperation(
        name: 'update_facility_model',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Model\UpdateFacilityModel\UpdateFacilityModelCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Model\UpdateFacilityModel\UpdateFacilityModelResult::class,
        contractClasses: [\Facility\Application\Contract\Model\FacilityModelView::class],
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'facility_model',
      ),
      new HttpIdempotencyOperation(
        name: 'activate_facility_model',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Model\ActivateFacilityModel\ActivateFacilityModelCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Model\ActivateFacilityModel\ActivateFacilityModelResult::class,
        contractClasses: [\Facility\Application\Contract\Model\FacilityModelView::class],
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'facility_model',
      ),
      new HttpIdempotencyOperation(
        name: 'delete_facility_model',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Model\DeleteFacilityModel\DeleteFacilityModelCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Model\DeleteFacilityModel\DeleteFacilityModelResult::class,
        statusCode: 204,
        requiredKey: true,
        resourceKind: 'facility_model',
      ),
      new HttpIdempotencyOperation(
        name: 'facility_create',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Facility\CreateFacility\CreateFacilityCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Facility\CreateFacility\CreateFacilityResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'facilityId',
        resourceKind: 'facility',
      ),
      new HttpIdempotencyOperation(
        name: 'facility_update',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Facility\UpdateFacility\UpdateFacilityCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Facility\UpdateFacility\UpdateFacilityResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'facility',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityQuery::class, \Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult::class, [], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'facility_archive',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Facility\ArchiveFacility\ArchiveFacilityCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Facility\ArchiveFacility\ArchiveFacilityResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'facility',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityQuery::class, \Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult::class, [], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'facility_restore',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Facility\RestoreFacility\RestoreFacilityCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Facility\RestoreFacility\RestoreFacilityResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'facility',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityQuery::class, \Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult::class, [], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'facility_move',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Facility\MoveFacility\MoveFacilityCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Facility\MoveFacility\MoveFacilityResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'facility',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityQuery::class, \Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult::class, [], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'facility_plan_geometry_set',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Facility\SetFacilityPlanGeometry\SetFacilityPlanGeometryCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Facility\SetFacilityPlanGeometry\SetFacilityPlanGeometryResult::class,
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'facility',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityQuery::class, \Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult::class, [], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'facility_duplicate_subtree',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Facility\Application\UseCase\Command\Facility\DuplicateFacilitySubtree\DuplicateFacilitySubtreeCommand::class,
        resultClass: \Facility\Application\UseCase\Command\Facility\DuplicateFacilitySubtree\DuplicateFacilitySubtreeResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'facilityId',
        resourceKind: 'facility',
        queryProjections: [
          new HttpIdempotencyQueryProjection(\Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityQuery::class, \Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult::class, [], captureBeforePrimary: false, refreshBeforeCommit: true),
        ],
      ),
    ];
  }
  // #endregion
}
