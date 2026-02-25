<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use LogicException;
use Onboarding\Application\Port\Inbound\OrganizationOnboardingServicePort;
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Mapper\Onboarding\OrganizationOnboardingOutputAssembler;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
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
 *
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
   * @param mixed $data null (no request body)
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationOnboardingOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $stepKey = $uriVariables['stepKey'] ?? null;
    if (!is_string($stepKey) || '' === $stepKey) {
      throw new BadRequestHttpException('stepKey URI parameter is required.');
    }

    try {
      return OrganizationOnboardingOutputAssembler::fromState(
        $this->flowService->skipStep(
          userId: $user->getId(),
          stepKey: $stepKey,
        ),
      );
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (LogicException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $logic = $this->findException($exception, LogicException::class);
      if ($logic instanceof LogicException) {
        throw new ConflictHttpException($logic->getMessage(), $exception);
      }

      throw $exception;
    }
  }
  // #endregion
}
