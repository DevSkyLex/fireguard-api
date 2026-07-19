<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Query\ListImportJobs;

use Import\Application\UseCase\Query\GetImportJob\GetImportJobResult;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListImportJobsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListImportJobsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<GetImportJobResult> $items the page items
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   * @param int $total the total matching import job count
   */
  public function __construct(
    public array $items,
    public int $page,
    public int $itemsPerPage,
    public int $total,
  ) {
  }
  // #endregion
}
