<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Federation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\Federation\FederatedProvider;
use Auth\Application\Contract\User\AuthenticatedUser;
use Auth\Application\UseCase\Command\Federation\DisconnectFederation\DisconnectFederationCommand;
use Auth\Presentation\Api\Dto\Output\Federation\FederatedConnectionsOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/**
 * Class FederatedDisconnectProcessor.
 *
 * Removes one federated identity connection for the authenticated user while preserving the remaining connections.
 *
 * @category Processor
 *
 * @implements ProcessorInterface<void, FederatedConnectionsOutput>
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedDisconnectProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the federation service and security context used to remove only the current user's provider connection.
   *
   * @access public
   *
   * @param CommandBusPort $federation removes the current user's provider connection
   * @param Security $security resolves the authenticated security user
   *
   * @return void
   */
  public function __construct(
    private CommandBusPort $federation,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   *
   * Removes the authenticated user's connection to the requested identity provider.
   *
   * @access public
   *
   * @param mixed $data the processor input
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables the route variables containing the provider
   * @param array<string, mixed> $context the processor context
   *
   * @return FederatedConnectionsOutput the remaining provider connections
   *
   * @throws AccessDeniedHttpException when no authenticated security user is available
   * @throws BadRequestHttpException when the provider identifier is unknown
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FederatedConnectionsOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $value = $uriVariables['provider'] ?? null;
    $provider = is_string($value) ? FederatedProvider::tryFrom($value) : null;
    if (null === $provider) {
      throw new BadRequestHttpException('unknown_provider');
    }

    /** @var \Auth\Application\UseCase\Command\Federation\DisconnectFederation\DisconnectFederationResult $result */
    $result = $this->federation->dispatch(new DisconnectFederationCommand($provider, $user->getId()));

    return FederatedConnectionsOutput::fromContract($result->connections);
  }
  // #endregion
}
