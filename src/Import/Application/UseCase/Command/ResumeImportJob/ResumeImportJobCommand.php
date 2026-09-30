<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Command\ResumeImportJob;

use Shared\Application\Message\CommandMessage;

/**
 * Class ResumeImportJobCommand
 *
 * Requests resumption of a retained import job by its owning user.
 *
 * @category Command
 */
final readonly class ResumeImportJobCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the authenticated user and retained import job to resume.
   *
   * @access public
   *
   * @param string $userId the user requesting resumption
   * @param string $importJobId the retained import job identifier
   *
   * @return void
   */
  public function __construct(public string $userId, public string $importJobId)
  {
  }
  // #endregion
}
