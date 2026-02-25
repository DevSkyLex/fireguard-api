<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Onboarding\Application\Port\Inbound\OrganizationOnboardingServicePort;
use Onboarding\Presentation\Api\Dto\Input\Onboarding\StartOrganizationOnboardingInput;
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Mapper\Onboarding\OrganizationOnboardingOutputAssembler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Processor StartOrganizationOnboardingProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<StartOrganizationOnboardingInput, OrganizationOnboardingOutput>
 */
final readonly class StartOrganizationOnboardingProcessor implements ProcessorInterface
{
  // #region Constructor
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
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationOnboardingOutput
  {
    /** @var StartOrganizationOnboardingInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return OrganizationOnboardingOutputAssembler::fromState(
      $this->flowService->start(
        userId: $user->getId(),
        reset: $data->reset,
      ),
    );
  }
  // #endregion
}
