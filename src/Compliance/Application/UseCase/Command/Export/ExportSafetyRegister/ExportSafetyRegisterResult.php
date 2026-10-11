<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Command\Export\ExportSafetyRegister;

use Shared\Application\Message\ResultMessage;

/**
 * Class ExportSafetyRegisterResult
 *
 * Contains rendered PDF bytes without an HTTP or renderer dependency.
 *
 * @category UseCase
 */
final readonly class ExportSafetyRegisterResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @return void
   */
  public function __construct(public string $contents, public string $fileName)
  {
  }
  // #endregion
}
