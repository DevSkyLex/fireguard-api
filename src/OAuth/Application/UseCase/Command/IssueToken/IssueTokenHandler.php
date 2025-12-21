<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\IssueToken;

use OAuth\Application\Port\Outbound\AuthorizationServerPort;
use Shared\Application\Message\CommandHandler;

/**
 * Handler IssueTokenHandler.
 *
 * @category Handler
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IssueTokenHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * IssueTokenHandler class.
   *
   * @since 1.0.0
   *
   * @param AuthorizationServerPort $authorizationServer the authorization server port
   */
  public function __construct(
    private readonly AuthorizationServerPort $authorizationServer,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   * {@inheritdoc}
   *
   * Handles the IssueTokenCommand.
   *
   * @since 1.0.0
   *
   * @param IssueTokenCommand $command the command
   *
   * @return IssueTokenResult the result
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
      codeVerifier: $command->codeVerifier,
    );
  }

  // #endregion
}
