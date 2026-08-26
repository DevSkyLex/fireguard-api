<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Mapper;

use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId, InspectionResponseId, InspectionResponseStatus};
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionResponseRecord;
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Mapper InspectionResponseMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionResponseMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * `organization` is a non-nullable foreign key, so the guard below is a
   * programming-error assertion rather than a reachable branch — an
   * unhydrated association here would mean the mapping changed.
   *
   * @since 1.0.0
   *
   * @param InspectionResponseRecord $record the persisted record
   *
   * @return InspectionResponse the response aggregate
   */
  public static function toDomain(InspectionResponseRecord $record): InspectionResponse
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new LogicException('Inspection response record must reference an organization.');
    }

    return InspectionResponse::reconstitute(
      id: InspectionResponseId::fromString($record->id),
      organizationId: InspectionOrganizationId::fromString($record->organization->id),
      inspectionId: InspectionId::fromString($record->inspectionId),
      interventionId: $record->interventionId,
      clientId: $record->clientId,
      status: InspectionResponseStatus::from($record->recordStatus),
      revision: $record->revision,
      itemKey: $record->itemKey,
      value: $record->value,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
    );
  }

  /**
   * Method applyTo.
   *
   * Writes the aggregate onto a record. The `organization` association is
   * NOT set here: only the repository holds an entity manager, and only it
   * can turn an identifier into a reference.
   *
   * @since 1.0.0
   *
   * @param InspectionResponse $response the response aggregate
   * @param InspectionResponseRecord $record the record to fill
   */
  public static function applyTo(InspectionResponse $response, InspectionResponseRecord $record): void
  {
    $record->id = (string) $response->id();
    $record->interventionId = $response->interventionId();
    $record->inspectionId = (string) $response->inspectionId();
    $record->clientId = $response->clientId();
    $record->recordStatus = $response->status()->value;
    $record->revision = $response->revision();
    $record->itemKey = $response->itemKey();
    $record->value = $response->value();
    $record->createdAt = $response->createdAt();
    $record->updatedAt = $response->updatedAt();
  }
  // #endregion
}
