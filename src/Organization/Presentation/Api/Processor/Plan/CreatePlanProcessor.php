<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Plan;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use InvalidArgumentException;
use Organization\Application\UseCase\Command\Plan\CreatePlan\{CreatePlanCommand, CreatePlanResult};
use Organization\Application\UseCase\Query\Plan\GetPlan\{GetPlanQuery, GetPlanResult};
use Organization\Domain\Exception\{PlanKeyAlreadyExistsException, PlanNotFoundException};
use Organization\Presentation\Api\Dto\Input\Plan\CreatePlanInput;
use Organization\Presentation\Api\Dto\Output\Plan\PlanOutput;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Processor CreatePlanProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<CreatePlanInput, PlanOutput>
 */
final readonly class CreatePlanProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreatePlanProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Creates a plan and returns the created plan output.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PlanOutput
  {
    /** @var CreatePlanInput $data */
    try {
      /** @var CreatePlanResult $result */
      $result = $this->commandBus->dispatch(new CreatePlanCommand(
        key: $data->key,
        name: $data->name,
        limits: $data->limits,
        description: $data->description,
        isActive: $data->isActive,
        isDefault: $data->isDefault,
        sortOrder: $data->sortOrder,
      ));
    } catch (PlanKeyAlreadyExistsException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException|InvalidValueException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $this->rethrowDomainFailure($exception);

      throw $exception;
    }

    try {
      /** @var GetPlanResult $plan */
      $plan = $this->queryBus->ask(new GetPlanQuery($result->planId));
    } catch (PlanNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    return PlanOutput::fromResult($plan);
  }

  /**
   * Method rethrowDomainFailure.
   *
   * Unwraps a messenger runtime failure and rethrows the matching HTTP error.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   */
  private function rethrowDomainFailure(Throwable $exception): void
  {
    $current = $exception;

    while (null !== $current) {
      foreach ($this->wrappedExceptions($current) as $candidate) {
        if ($candidate instanceof PlanKeyAlreadyExistsException) {
          throw new ConflictHttpException($candidate->getMessage(), $exception);
        }

        if ($candidate instanceof InvalidArgumentException || $candidate instanceof InvalidValueException) {
          throw new BadRequestHttpException($candidate->getMessage(), $exception);
        }
      }

      $current = $current->getPrevious();
    }
  }

  /**
   * Method wrappedExceptions.
   *
   * Yields the exception itself and any handler-wrapped exceptions.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception to expand
   *
   * @return iterable<Throwable> the candidate exceptions
   */
  private function wrappedExceptions(Throwable $exception): iterable
  {
    yield $exception;

    if ($exception instanceof HandlerFailedException) {
      yield from $exception->getWrappedExceptions();
    }
  }
  // #endregion
}
