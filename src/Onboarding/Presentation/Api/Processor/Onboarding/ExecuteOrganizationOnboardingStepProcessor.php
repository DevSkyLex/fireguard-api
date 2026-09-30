<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use LogicException;
use Onboarding\Application\Port\Inbound\OrganizationOnboardingServicePort;
use Onboarding\Application\Service\ExecuteOnboardingStepPayload;
use Onboarding\Presentation\Api\Dto\Input\Onboarding\ExecuteOrganizationOnboardingStepInput;
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Mapper\Onboarding\OrganizationOnboardingOutputAssembler;
use Organization\Domain\Exception\{
  OrganizationNotFoundException,
  OrganizationSlugAlreadyExistsException
};
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException
};
use ValueError;

use function is_string;

/**
 * Processor ExecuteOrganizationOnboardingStepProcessor.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<ExecuteOrganizationOnboardingStepInput, OrganizationOnboardingOutput>
 */
final readonly class ExecuteOrganizationOnboardingStepProcessor implements ProcessorInterface
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Method __construct
   *
   * Connects authenticated onboarding-step requests to the onboarding application service.
   *
   * @access public
   *
   * @param OrganizationOnboardingServicePort $flowService executes organization onboarding steps
   * @param Security $security resolves the authenticated user
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
   * Checks the authenticated actor and executes the requested organization onboarding step.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ExecuteOrganizationOnboardingStepInput $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return OrganizationOnboardingOutput the updated onboarding state
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationOnboardingOutput
  {
    /**
     * @var ExecuteOrganizationOnboardingStepInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $stepKey = $uriVariables['stepKey'] ?? null;
    if (!is_string($stepKey) || '' === $stepKey) {
      throw new BadRequestHttpException('stepKey URI parameter is required.');
    }

    $payload = new ExecuteOnboardingStepPayload();

    try {
      return OrganizationOnboardingOutputAssembler::fromState(
        $this->flowService->executeStep(
          userId: $user->getId(),
          stepKey: $stepKey,
          input: $payload,
        ),
      );
    } catch (OrganizationSlugAlreadyExistsException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException|InvalidValueException|ValueError $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (LogicException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $this->rethrowMessengerFailure($exception);
    }
  }

  /**
   * Method rethrowMessengerFailure
   *
   * Maps recognized organization setup failures to HTTP exceptions and rethrows unrecognized failures.
   *
   * @access private
   *
   * @param MessengerRuntimeException $exception the message bus failure to inspect
   *
   * @return never this method always throws
   */
  private function rethrowMessengerFailure(MessengerRuntimeException $exception): never
  {
    $slugConflict = $this->findException($exception, OrganizationSlugAlreadyExistsException::class);
    if ($slugConflict instanceof OrganizationSlugAlreadyExistsException) {
      throw new ConflictHttpException($slugConflict->getMessage(), $exception);
    }
    $notFound = $this->findException($exception, OrganizationNotFoundException::class);
    if ($notFound instanceof OrganizationNotFoundException) {
      throw new NotFoundHttpException($notFound->getMessage(), $exception);
    }
    $invalidArgument = $this->findException($exception, InvalidArgumentException::class);
    if ($invalidArgument instanceof InvalidArgumentException) {
      throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
    }
    $invalidValue = $this->findException($exception, InvalidValueException::class);
    if ($invalidValue instanceof InvalidValueException) {
      throw new BadRequestHttpException($invalidValue->getMessage(), $exception);
    }
    $valueError = $this->findException($exception, ValueError::class);
    if ($valueError instanceof ValueError) {
      throw new BadRequestHttpException($valueError->getMessage(), $exception);
    }
    $logicException = $this->findException($exception, LogicException::class);
    if ($logicException instanceof LogicException) {
      throw new ConflictHttpException($logicException->getMessage(), $exception);
    }

    throw $exception;
  }
  // #endregion
}
