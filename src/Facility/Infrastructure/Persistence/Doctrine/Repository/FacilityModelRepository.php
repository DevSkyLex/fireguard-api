<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Outbound\FacilityModelRepositoryPort;
use Facility\Domain\Exception\FacilityModelException;
use Facility\Domain\Model\FacilityModel\FacilityModel;
use Facility\Infrastructure\Persistence\Doctrine\Mapper\FacilityModelMapper;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityModelRecord, FacilityRecord};

use function array_map;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Repository FacilityModelRepository. Main database atomic CAS and building-serialized activation.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityModelRepository implements FacilityModelRepositoryPort
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Initializes the model capability with its typed dependencies and state.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager
   *
   * @return void no return value
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method find.
   *
   * Reads a model snapshot by identifier without returning persistence objects.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $id the id
   *
   * @return ?FacilityModel the operation result
   */
  public function find(string $id): ?FacilityModel
  {
    $record = $this->entityManager->find(FacilityModelRecord::class, $id);
    if (null === $record) {
      return null;
    }
    $this->entityManager->refresh($record);

    return FacilityModelMapper::toDomain($record);
  }

  /**
   * Method findByBuilding.
   *
   * Lists immutable model snapshots in one organization and building scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $organizationId the organization id
   * @param string $buildingId the building id
   *
   * @return list<FacilityModel> the ordered model snapshots
   */
  public function findByBuilding(string $organizationId, string $buildingId): array
  {
    $records = $this->entityManager->getRepository(FacilityModelRecord::class)->findBy(
      ['organizationId' => $organizationId, 'building' => $buildingId],
      ['createdAt' => 'DESC', 'id' => 'ASC'],
    );

    return array_map(function (FacilityModelRecord $record): FacilityModel {
      $this->entityManager->refresh($record);

      return FacilityModelMapper::toDomain($record);
    }, $records);
  }

  /**
   * Method insert.
   *
   * Stores a draft model while serializing the per-building model limit.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModel $model the model
   *
   * @return void no return value
   */
  public function insert(FacilityModel $model): void
  {
    $this->entityManager->getConnection()->transactional(function (Connection $connection) use ($model): void {
      $this->lockBuilding($connection, $model);
      /** @var int|string $count */
      $count = $connection->fetchOne(
        'SELECT COUNT(*) FROM facility_models WHERE building_id = :building AND organization_id = :organization',
        ['building' => $model->buildingId, 'organization' => $model->organizationId],
      );
      if ((int) $count >= 2) {
        throw FacilityModelException::limit();
      }
      /** @var FacilityRecord $building */
      $building = $this->entityManager->getReference(FacilityRecord::class, $model->buildingId);
      $this->entityManager->persist(FacilityModelMapper::toRecord($model, $building));
      $this->entityManager->flush();
    });
  }

  /**
   * Method update.
   *
   * Persists settings only when the stored revision matches the expected revision.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModel $model the model
   * @param int $expectedRevision the expected revision
   *
   * @return void no return value
   */
  public function update(FacilityModel $model, int $expectedRevision): void
  {
    $rows = $this->entityManager->getConnection()->executeStatement(
      'UPDATE facility_models SET transform = CAST(:transform AS jsonb), bindings = CAST(:bindings AS jsonb), revision = :revision, updated_at = :updated
       WHERE id = :id AND organization_id = :organization AND building_id = :building AND revision = :expected',
      ['transform' => json_encode($model->transform->toArray(), JSON_THROW_ON_ERROR),
        'bindings' => json_encode($model->bindings, JSON_THROW_ON_ERROR), 'revision' => $model->revision,
        'updated' => $model->updatedAt->format('Y-m-d H:i:s'), 'id' => $model->id,
        'organization' => $model->organizationId, 'building' => $model->buildingId, 'expected' => $expectedRevision],
    );
    if (1 !== $rows) {
      throw FacilityModelException::stale();
    }
  }

  /**
   * Method activate.
   *
   * Activates the selected model while preserving revision preconditions.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModel $model the model
   * @param int $expectedRevision the expected revision
   *
   * @return void no return value
   */
  public function activate(FacilityModel $model, int $expectedRevision): void
  {
    $this->entityManager->getConnection()->transactional(function (Connection $connection) use ($model, $expectedRevision): void {
      $this->lockBuilding($connection, $model);
      /** @var int|string|false $revision */
      $revision = $connection->fetchOne(
        'SELECT revision FROM facility_models WHERE id = :id AND organization_id = :organization AND building_id = :building FOR UPDATE',
        ['id' => $model->id, 'organization' => $model->organizationId, 'building' => $model->buildingId],
      );
      if (false === $revision || (int) $revision !== $expectedRevision) {
        throw FacilityModelException::stale();
      }
      $connection->executeStatement(
        'UPDATE facility_models SET active = FALSE, active_building_id = NULL, revision = revision + 1, updated_at = :updated
        WHERE building_id = :building AND organization_id = :organization AND id != :id AND active = TRUE',
        ['updated' => $model->updatedAt->format('Y-m-d H:i:s'), 'building' => $model->buildingId,
          'organization' => $model->organizationId, 'id' => $model->id],
      );
      $connection->executeStatement(
        'UPDATE facility_models SET active = TRUE, active_building_id = :building, revision = :revision, updated_at = :updated WHERE id = :id',
        ['building' => $model->buildingId, 'revision' => $model->revision,
          'updated' => $model->updatedAt->format('Y-m-d H:i:s'), 'id' => $model->id],
      );
    });
  }

  /**
   * Method delete.
   *
   * Removes the model only when the stored revision matches the expected revision.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModel $model the model
   * @param int $expectedRevision the expected revision
   *
   * @return void no return value
   */
  public function delete(FacilityModel $model, int $expectedRevision): void
  {
    $rows = $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM facility_models WHERE id = :id AND organization_id = :organization AND building_id = :building AND revision = :expected',
      ['id' => $model->id, 'organization' => $model->organizationId, 'building' => $model->buildingId, 'expected' => $expectedRevision],
    );
    if (1 !== $rows) {
      throw FacilityModelException::stale();
    }
    $record = $this->entityManager->getUnitOfWork()->tryGetById($model->id, FacilityModelRecord::class);
    if ($record instanceof FacilityModelRecord) {
      $this->entityManager->detach($record);
    }
  }

  /**
   * Method lockBuilding.
   *
   * Locks the owning published building to serialize imports and activation.
   *
   * @access private
   * @since 1.0.0
   *
   * @param Connection $connection the connection
   * @param FacilityModel $model the model
   *
   * @return void no return value
   */
  private function lockBuilding(Connection $connection, FacilityModel $model): void
  {
    $id = $connection->fetchOne(
      "SELECT id FROM facilities WHERE id = :building AND organization_id = :organization AND type = 'building' AND record_status = 'published' FOR UPDATE",
      ['building' => $model->buildingId, 'organization' => $model->organizationId],
    );
    if (false === $id) {
      throw FacilityModelException::notFound();
    }
  }
  // #endregion
}
