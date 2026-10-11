<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Federation\CompleteFederation;

use Auth\Application\Port\Inbound\Federation\FederationFlowPort;
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;

/**
 * CompleteFederationHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CompleteFederationHandler implements CommandHandler
{
  public function __construct(private FederationFlowPort $flows, private LoggerPort $logger)
  {
  }

  public function __invoke(CompleteFederationCommand $command): CompleteFederationResult
  {
    try {
      if (null !== $command->userId) {
        return new CompleteFederationResult(connections: $this->flows->completeLink($command->provider, $command->state, $command->code, $command->userId, $command->browserBinding, $command->providerError));
      }

      return new CompleteFederationResult(login: $this->flows->completeLogin($command->provider, $command->state, $command->code, $command->browserBinding, $command->providerError, $command->loginContext));
    } catch (FederatedAuthException $exception) {
      throw $exception;
    } catch (Throwable $exception) {
      $this->logger->warning('Federated authentication provider exchange failed.', [
        'provider' => $command->provider->value,
        'exception_class' => $exception::class,
      ]);

      throw new FederatedAuthException('federated_auth_failed', 'Federated authentication failed.');
    }

  }
}
