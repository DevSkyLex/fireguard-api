<?php

declare(strict_types=1);

namespace Auth\Application\Port\Inbound;

use Auth\Application\UseCase\Command\IssueToken\IssueTokenCommand;
use Auth\Application\UseCase\Command\IssueToken\IssueTokenResult;

/**
 * Interface IssueTokenUseCasePort
 *
 * Inbound port for OAuth2 token issuance use case.
 *
 * @category Port
 * @package Auth\Application\Port\Inbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface IssueTokenUseCasePort
{
  //#region Methods
  /**
   * Method execute
   *
   * Issue OAuth2 access and refresh tokens.
   *
   * @access public
   * @since 1.0.0
   *
   * @param IssueTokenCommand $command The issue token command.
   *
   * @return IssueTokenResult The token issuance result.
   */
  public function execute(IssueTokenCommand $command): IssueTokenResult;
  //#endregion
}
