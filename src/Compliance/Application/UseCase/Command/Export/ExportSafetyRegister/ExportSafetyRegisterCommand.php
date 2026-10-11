<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Command\Export\ExportSafetyRegister;

use Shared\Application\Message\CommandMessage;

/**
 * Class ExportSafetyRegisterCommand
 *
 * Identifies the authorized register export and its optional facility scope.
 *
 * @category UseCase
 */
final readonly class ExportSafetyRegisterCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @return void
   */
  public function __construct(public string $organizationId, public string $userId, public ?string $facilityId = null)
  {
  }
  // #endregion
}
