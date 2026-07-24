<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Query\GetImportJob;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetImportJobQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetImportJobQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user identifier
   * @param string $importJobId the import job identifier
   */
  public function __construct(
    public string $userId,
    public string $importJobId,
  ) {
  }
  // #endregion
}
