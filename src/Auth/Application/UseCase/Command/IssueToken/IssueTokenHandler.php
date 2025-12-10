<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\IssueToken;

use Auth\Application\Port\Outbound\AuthorizationServerPort;
use Shared\Application\Message\CommandHandler;

/**
 * Handler IssueTokenHandler
 * @final
 *
 * Handler for IssueTokenCommand.
 * Uses the AuthorizationServerPort to abstract the OAuth2 server implementation.
 *
 * @category Handler
 * @package Auth\Application\UseCase\Command\IssueToken
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IssueTokenHandler implements CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * IssueTokenHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AuthorizationServerPort $authorizationServer The authorization server port.
   */
  public function __construct(
    private readonly AuthorizationServerPort $authorizationServer
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   * {@inheritdoc}
   *
   * Handles the IssueTokenCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param IssueTokenCommand $command The command.
   *
   * @return IssueTokenResult The result.
   */
  public function __invoke(IssueTokenCommand $command): IssueTokenResult
  {
    return $this->authorizationServer->issueAccessToken(
      grantType: $command->grantType,
      clientId: $command->clientId,
      clientSecret: $command->clientSecret,
      scope: $command->scope,
      refreshToken: $command->refreshToken,
      code: $command->code,
      redirectUri: $command->redirectUri,
      codeVerifier: $command->codeVerifier
    );
  }

  //#endregion
}
