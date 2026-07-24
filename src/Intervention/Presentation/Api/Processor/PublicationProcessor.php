<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Command\Publication\RequestPublication\{RequestPublicationCommand, RequestPublicationResult};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionBlockedException, InterventionConflictException, InterventionNotFoundException};
use Intervention\Presentation\Api\Dto\Input\CreatePublicationInput;
use Intervention\Presentation\Api\Dto\Output\PublicationOutput;
use Intervention\Presentation\Api\Factory\PublicationOutputFactory;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, ConflictHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

/**
 * Processor PublicationProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, PublicationOutput>
 */
final readonly class PublicationProcessor implements ProcessorInterface
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the PublicationProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param PublicationOutputFactory $outputMapper the output mapper value
   * @param Security $security the security value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private PublicationOutputFactory $outputMapper,
    private Security $security,
  ) {
  }

  /**
   * Method process.
   *
   * Executes the process operation.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return PublicationOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PublicationOutput
  {
    /** @var CreatePublicationInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      /** @var RequestPublicationResult $result */
      $result = $this->commandBus->dispatch(new RequestPublicationCommand(
        $user->getId(),
        ResourceIriParser::id($data->intervention, 'interventions'),
        $data->interventionRevision,
      ));
    } catch (MessengerRuntimeException $exception) {
      throw $this->mapException($exception);
    } catch (InterventionAccessDeniedException|InterventionBlockedException|InterventionConflictException|InterventionNotFoundException $exception) {
      throw $this->mapException($exception);
    }

    return $this->outputMapper->fromView($result->publication);
  }

  /**
   * Method mapException.
   *
   * Executes the map exception operation.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the map exception result
   */
  private function mapException(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      $mapped = match (true) {
        $current instanceof InterventionAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof InterventionNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof InterventionConflictException => new ConflictHttpException($current->getMessage(), $exception),
        $current instanceof InterventionBlockedException => new UnprocessableEntityHttpException($current->getMessage(), $exception),
        default => null,
      };
      if ($mapped instanceof Throwable) {
        return $mapped;
      }
      $current = $current->getPrevious();
    } while ($current instanceof Throwable);

    return $exception;
  }
}
