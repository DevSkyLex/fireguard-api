<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Onboarding\Application\Port\Inbound\OrganizationOnboardingServicePort;
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Mapper\Onboarding\OrganizationOnboardingOutputAssembler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Processor DismissOrganizationOnboardingProcessor.
 *
 * Voluntarily hides the non-blocking activation flow for the authenticated user.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<null, OrganizationOnboardingOutput>
 */
final readonly class DismissOrganizationOnboardingProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Dispatches the authenticated user’s request to hide organization onboarding.
   *
   * @access public
   *
   * @param OrganizationOnboardingServicePort $flowService service port for organization onboarding state transitions
   * @param Security $security security context used to identify the authenticated user
   *
   * @return void
   */
  public function __construct(
    private OrganizationOnboardingServicePort $flowService,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Checks the authenticated actor and dismisses the requested organization onboarding flow.
   *
   * @access public
   * @since 1.0.0
   *
   * @param null $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return OrganizationOnboardingOutput the dismissed onboarding state
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationOnboardingOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return OrganizationOnboardingOutputAssembler::fromState(
      $this->flowService->dismiss($user->getId()),
    );
  }
  // #endregion
}
