<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Onboarding\Application\UseCase\Command\Flow\StartOrganizationOnboarding\{StartOrganizationOnboardingCommand, StartOrganizationOnboardingResult};
use Onboarding\Presentation\Api\Dto\Input\Onboarding\StartOrganizationOnboardingInput;
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Mapper\Onboarding\OrganizationOnboardingOutputAssembler;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Processor StartOrganizationOnboardingProcessor.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<StartOrganizationOnboardingInput, OrganizationOnboardingOutput>
 */
final readonly class StartOrganizationOnboardingProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Starts or resets onboarding for the authenticated user.
   *
   * @access public
   *
   * @param CommandBusPort $commandBus owns the durable onboarding transition and result
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
   * Method process.
   *
   * Checks the authenticated actor and starts organization onboarding.
   *
   * @access public
   * @since 1.0.0
   *
   * @param StartOrganizationOnboardingInput $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return OrganizationOnboardingOutput the started onboarding state
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationOnboardingOutput
  {
    /**
     * @var StartOrganizationOnboardingInput $data
     */
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    /** @var StartOrganizationOnboardingResult $result */
    $result = $this->commandBus->dispatch(new StartOrganizationOnboardingCommand(
      userId: $user->getId(),
      reset: $data->reset,
      intent: $data->intent,
    ));

    return OrganizationOnboardingOutputAssembler::fromState($result->state);
  }
  // #endregion
}
