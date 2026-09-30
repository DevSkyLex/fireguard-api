<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Provider\Federation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Service\Federation\FederatedAuthenticationService;
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Presentation\Api\Dto\Output\Federation\FederatedConnectionsOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class FederatedConnectionsProvider.
 *
 * Reads the authenticated user's linked federated identity providers for the account settings view.
 *
 * @category Provider
 *
 * @implements ProviderInterface<FederatedConnectionsOutput>
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedConnectionsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the connection reader and current security context used to scope the provider result to its owner.
   *
   * @access public
   *
   * @param FederatedAuthenticationService $federation reads the user's provider connections
   * @param Security $security resolves the authenticated security user
   *
   * @return void
   */
  public function __construct(
    private FederatedAuthenticationService $federation,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   *
   * Reads the authenticated user's federated identity provider connections.
   *
   * @access public
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables the route variables
   * @param array<string, mixed> $context the provider context
   *
   * @return FederatedConnectionsOutput the user's provider connections
   *
   * @throws AccessDeniedHttpException when no authenticated security user is available
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): FederatedConnectionsOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return FederatedConnectionsOutput::fromContract($this->federation->connections($user->getId()));
  }
  // #endregion
}
