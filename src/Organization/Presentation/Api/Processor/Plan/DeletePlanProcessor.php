<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Plan;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use InvalidArgumentException;
use Organization\Application\UseCase\Command\Plan\DeletePlan\DeletePlanCommand;
use Organization\Domain\Exception\PlanNotFoundException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/**
 * Processor DeletePlanProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, null>
 */
final readonly class DeletePlanProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeletePlanProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   */
  public function __construct(
    private CommandBusPort $commandBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Deletes a plan from the catalog.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
  {
    $planId = $uriVariables['id'] ?? null;
    if (!is_string($planId) || '' === $planId) {
      throw new BadRequestHttpException('Plan identifier is required.');
    }

    try {
      $this->commandBus->dispatch(new DeletePlanCommand($planId));
    } catch (PlanNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $this->rethrowDomainFailure($exception);

      throw $exception;
    }

    return null;
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
        if ($candidate instanceof PlanNotFoundException) {
          throw new NotFoundHttpException($candidate->getMessage(), $exception);
        }

        if ($candidate instanceof InvalidArgumentException) {
          throw new ConflictHttpException($candidate->getMessage(), $exception);
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
