<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Model\DownloadFacilityModel;

use Facility\Application\Service\FacilityModelAccessGuard;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\FileStoragePort;

/** UseCase DownloadFacilityModelHandler.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DownloadFacilityModelHandler implements QueryHandler
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
   * @param FileStoragePort $storage the storage
   *
   * @return void no return value
   */
  public function __construct(private FacilityModelAccessGuard $access, private FileStoragePort $storage)
  {
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
   * @param DownloadFacilityModelQuery $query the query
   *
   * @return DownloadFacilityModelResult the operation result
   */
  public function __invoke(DownloadFacilityModelQuery $query): DownloadFacilityModelResult
  {
    $model = $this->access->model($query->userId, $query->modelId, false);

    return new DownloadFacilityModelResult($this->storage->read($model->storagePath), $model->fileName);
  }
  // #endregion
}
