<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Plan;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use InvalidArgumentException;
use Organization\Application\UseCase\Command\Plan\DeletePlan\DeletePlanCommand;
use Organization\Domain\Exception\PlanNotFoundException;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

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
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * The bus adapters wrap every handler failure into
   * `MessengerRuntimeException`, so the direct `catch` clauses only cover a
   * bare in-process throw. The `MessengerRuntimeException` clauses using
   * this trait are what map the real dispatch path.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;
  // #endregion

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
      $notFound = $this->findWrappedException($exception, PlanNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $conflict = $this->findWrappedException($exception, InvalidArgumentException::class);
      if (null !== $conflict) {
        throw new ConflictHttpException($conflict->getMessage(), $exception);
      }

      throw $exception;
    }

    return null;
  }

  // #endregion
}
