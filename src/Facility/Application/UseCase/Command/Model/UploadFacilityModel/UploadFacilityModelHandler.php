<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Model\UploadFacilityModel;

use Facility\Application\Contract\Model\FacilityModelView;
use Facility\Application\Port\Outbound\FacilityModelRepositoryPort;
use Facility\Application\Service\FacilityModelAccessGuard;
use Facility\Domain\Exception\FacilityModelException;
use Facility\Domain\Model\FacilityModel\FacilityModel;
use Facility\Domain\ValueObject\GlbDocument;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{FileStoragePort, UuidGeneratorPort};
use Throwable;

use function count;
use function strlen;

/**
 * UseCase UploadFacilityModelHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UploadFacilityModelHandler implements CommandHandler
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
   * @param FacilityModelAccessGuard $access the access
   * @param FacilityModelRepositoryPort $models the models
   * @param FileStoragePort $storage the storage
   * @param UuidGeneratorPort $uuids the uuids
   *
   * @return void no return value
   */
  public function __construct(
    private FacilityModelAccessGuard $access,
    private FacilityModelRepositoryPort $models,
    private FileStoragePort $storage,
    private UuidGeneratorPort $uuids,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Executes the requested operation and returns its typed result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param UploadFacilityModelCommand $command the command
   *
   * @return UploadFacilityModelResult the operation result
   */
  public function __invoke(UploadFacilityModelCommand $command): UploadFacilityModelResult
  {
    $this->access->building($command->userId, $command->organizationId, $command->buildingId, true);
    if (count($this->models->findByBuilding($command->organizationId, $command->buildingId)) >= 2) {
      throw FacilityModelException::limit();
    }
    $document = GlbDocument::fromContents($command->contents);
    $model = FacilityModel::create(
      $this->uuids->generate(),
      $command->organizationId,
      $command->buildingId,
      $command->fileName,
      strlen($command->contents),
      $document->nodes,
    );
    $this->storage->write($model->storagePath, $command->contents);

    try {
      $this->models->insert($model);
    } catch (Throwable $exception) {
      $this->storage->delete($model->storagePath);

      throw $exception;
    }

    return new UploadFacilityModelResult(FacilityModelView::fromModel($model));
  }
  // #endregion
}
