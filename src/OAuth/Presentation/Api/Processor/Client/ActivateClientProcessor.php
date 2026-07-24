<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Client;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use InvalidArgumentException;
use OAuth\Application\UseCase\Command\Client\ActivateClient\ActivateClientCommand;
use OAuth\Application\UseCase\Query\Client\GetClient\{GetClientQuery, GetClientResult};
use OAuth\Domain\Exception\Client\InvalidClientException;
use OAuth\Presentation\Api\Dto\Output\Client\ClientOutput;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/**
 * Processor ActivateClientProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, ClientOutput>
 */
final readonly class ActivateClientProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ActivateClientProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
    private readonly QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the client activation.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data (not used)
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return ClientOutput the processed output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ClientOutput
  {
    $id = $uriVariables['id'] ?? null;

    if (!is_string($id)) {
      throw new InvalidArgumentException('Client ID must be a string');
    }

    // Dispatch command
    $command = new ActivateClientCommand(clientId: $id);

    try {
      $this->commandBus->dispatch($command);
    } catch (InvalidClientException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findInvalidClientException($exception);
      if ($notFound instanceof InvalidClientException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      throw $exception;
    }

    // Fetch updated client
    $query = new GetClientQuery(clientId: $id);
    /** @var GetClientResult $result */
    $result = $this->queryBus->ask(query: $query);

    // Create output DTO
    $output = new ClientOutput();
    $output->id = $result->id;
    $output->name = $result->name;
    $output->redirectUris = $result->redirectUris;
    $output->grantTypes = $result->grantTypes;
    $output->scopes = $result->scopes;
    $output->isActive = $result->isActive;
    $output->createdAt = $result->createdAt;

    return $output;
  }

  private function findInvalidClientException(Throwable $exception): ?InvalidClientException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof InvalidClientException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof InvalidClientException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }
  // #endregion
}
