<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Provider\Onboarding;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Onboarding\Application\UseCase\Command\Flow\SynchronizeOrganizationOnboarding\{SynchronizeOrganizationOnboardingCommand, SynchronizeOrganizationOnboardingResult};
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Mapper\Onboarding\OrganizationOnboardingOutputAssembler;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provider OrganizationOnboardingProvider.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationOnboardingOutput>
 */
final readonly class OrganizationOnboardingProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Loads onboarding progress for the authenticated user.
   *
   * @access public
   *
   * @param CommandBusPort $commandBus owns stateful synchronization and its durable result
   * @param Security $security security context used to identify the authenticated user
   *
   * @return void
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): OrganizationOnboardingOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    /** @var SynchronizeOrganizationOnboardingResult $result */
    $result = $this->commandBus->dispatch(new SynchronizeOrganizationOnboardingCommand($user->getId()));

    return OrganizationOnboardingOutputAssembler::fromState($result->state);
  }
  // #endregion
}
