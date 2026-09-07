<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Onboarding\Application\UseCase\Command\Setup\PrepareOrganizationSetup\{PrepareOrganizationSetupCommand, PrepareOrganizationSetupResult};
use Onboarding\Presentation\Api\Dto\Input\Onboarding\PrepareOrganizationSetupInput;
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Mapper\Onboarding\OrganizationOnboardingOutputAssembler;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Durable organization setup recovery.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */

/** @implements ProcessorInterface<PrepareOrganizationSetupInput, OrganizationOnboardingOutput> */
final readonly class PrepareOrganizationSetupProcessor implements ProcessorInterface
{
  /**
   * @since 1.0.0
   */
  public function __construct(private CommandBusPort $commandBus, private Security $security)
  {
  }

  /**
   * @since 1.0.0
   *
   * @param array<string,mixed> $uriVariables route values
   * @param array<string,mixed> $context serializer context
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationOnboardingOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    /**
     * @var PrepareOrganizationSetupInput $data */
    /**
     * @var PrepareOrganizationSetupResult $result */
    $result = $this->commandBus->dispatch(new PrepareOrganizationSetupCommand($user->getId(), $data->sessionId, $data->stepKey, $data->items));

    return OrganizationOnboardingOutputAssembler::fromState($result->state);
  }
}
