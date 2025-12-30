<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\Token\IssueToken;

use OAuth\Application\Port\Outbound\Token\AuthorizationServerPort;
use OAuth\Domain\Event\Token\TokenIssuedEvent;
use OAuth\Domain\Event\Token\TokenIssueFailedEvent;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Throwable;

use function array_filter;
use function array_values;
use function explode;
use function is_string;
use function trim;

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
   * @param EventDispatcherPort $eventDispatcher the event dispatcher
   */
  public function __construct(
    private readonly AuthorizationServerPort $authorizationServer,
    private readonly EventDispatcherPort $eventDispatcher,
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
    try {
      $result = $this->authorizationServer->issueAccessToken(
        grantType: $command->grantType,
        clientId: $command->clientId,
        clientSecret: $command->clientSecret,
        scope: $command->scope,
        refreshToken: $command->refreshToken,
        code: $command->code,
        redirectUri: $command->redirectUri,
        codeVerifier: $command->codeVerifier,
      );

      $scopes = [];
      $scopeValue = $result->scope;
      if (is_string($scopeValue) && '' !== trim($scopeValue)) {
        $scopes = array_values(array_filter(explode(' ', $scopeValue), static fn (string $value): bool => '' !== $value));
      }

      $this->eventDispatcher->dispatch(event: new TokenIssuedEvent(
        tokenId: $result->accessToken,
        grantType: $command->grantType,
        clientId: $command->clientId,
        userId: null,
        scopes: $scopes,
        expiresIn: $result->expiresIn,
        ipAddress: $command->ipAddress,
      ));

      return $result;
    } catch (Throwable $exception) {
      $this->eventDispatcher->dispatch(new TokenIssueFailedEvent(
        grantType: $command->grantType,
        clientId: $command->clientId,
        ipAddress: $command->ipAddress,
        reason: $exception->getMessage(),
      ));

      throw $exception;
    }
  }

  // #endregion
}
