<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use InvalidArgumentException;
use LogicException;
use Onboarding\Application\UseCase\Command\Flow\SkipOrganizationOnboardingStep\{SkipOrganizationOnboardingStepCommand, SkipOrganizationOnboardingStepResult};
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Mapper\Onboarding\OrganizationOnboardingOutputAssembler;
use Shared\Application\Contract\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException
};

use function is_string;

/**
 * Processor SkipOrganizationOnboardingStepProcessor.
 *
 * Handles {@code POST /api/onboarding/organization/steps/{stepKey}/skip}.
 * Marks a non-required onboarding step as voluntarily skipped so the flow
 * can advance to completion without the user executing it.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<null, OrganizationOnboardingOutput>
 */
final readonly class SkipOrganizationOnboardingStepProcessor implements ProcessorInterface
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Method __construct.
   *
   * Dispatches the authenticated user’s request to skip an optional onboarding step.
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
   * Checks the authenticated actor and marks the requested onboarding step as skipped.
   *
   * @access public
   * @since 1.0.0
   *
   * @param null $data null (no request body)
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return OrganizationOnboardingOutput the updated onboarding state
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationOnboardingOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $stepKey = $uriVariables['stepKey'] ?? null;
    if (!is_string($stepKey) || '' === $stepKey) {
      throw new BadRequestHttpException('stepKey URI parameter is required.');
    }

    try {
      /** @var SkipOrganizationOnboardingStepResult $result */
      $result = $this->commandBus->dispatch(new SkipOrganizationOnboardingStepCommand(
        userId: $user->getId(),
        stepKey: $stepKey,
      ));

      return OrganizationOnboardingOutputAssembler::fromState($result->state);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (LogicException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $invalidArgument = $this->findException($exception, InvalidArgumentException::class);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }
      $logic = $this->findException($exception, LogicException::class);
      if ($logic instanceof LogicException) {
        throw new ConflictHttpException($logic->getMessage(), $exception);
      }

      throw $exception;
    }
  }
  // #endregion
}
