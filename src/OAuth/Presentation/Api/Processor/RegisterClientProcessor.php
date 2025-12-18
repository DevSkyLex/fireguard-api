<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use OAuth\Application\UseCase\Command\RegisterClient\{RegisterClientCommand, RegisterClientResult};
use OAuth\Presentation\Api\Dto\Input\ClientInput;
use OAuth\Presentation\Api\Dto\Output\ClientOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use OAuth\Domain\ValueObject\GrantTypes;
use OAuth\Domain\ValueObject\RedirectUri;
use OAuth\Domain\ValueObject\Scopes;

/**
 * Processor RegisterClientProcessor
 * @final
 *
 * API Platform processor for client registration.
 *
 * @category Processor
 * @package OAuth\Infrastructure\Adapter\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<ClientInput, ClientOutput>
 */
final readonly class RegisterClientProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * RegisterClientProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus The command bus.
   */
  public function __construct(
    private readonly CommandBusPort $commandBus
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the client registration.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientInput $data The input data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return ClientOutput The processed output.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ClientOutput
  {
    /** @var ClientInput $data */

    $name = $data->name ?? '';

    // Convert DTO to Command
    $command = new RegisterClientCommand(
      name: $name,
      redirectUris: array_map(fn(string $uri) => new RedirectUri($uri), $data->redirectUris),
      grantTypes: GrantTypes::fromArray($data->grantTypes),
      scopes: Scopes::fromArray($data->scopes)
    );

    // Dispatch command
    /** @var RegisterClientResult $result */
    $result = $this->commandBus->dispatch($command);

    // Create output DTO
    $output = new ClientOutput();
    $output->id = $result->clientId;
    $output->name = $data->name;
    $output->secret = $result->clientSecret;
    $output->redirectUris = $data->redirectUris;
    $output->grantTypes = $data->grantTypes;
    $output->scopes = $data->scopes;
    $output->isActive = true;
    $output->createdAt = date(format: 'Y-m-d H:i:s');

    return $output;
  }
  //#endregion
}
