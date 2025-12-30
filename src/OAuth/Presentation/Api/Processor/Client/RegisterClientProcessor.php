<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Client;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use OAuth\Application\UseCase\Command\Client\RegisterClient\{RegisterClientCommand, RegisterClientResult};
use OAuth\Domain\ValueObject\Client\RedirectUri;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Domain\ValueObject\Security\GrantTypes;
use OAuth\Presentation\Api\Dto\Input\Client\ClientInput;
use OAuth\Presentation\Api\Dto\Output\Client\ClientOutput;
use Shared\Application\Port\Inbound\CommandBusPort;

use function array_map;
use function date;

/**
 * Processor RegisterClientProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<ClientInput, ClientOutput>
 */
final readonly class RegisterClientProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RegisterClientProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the client registration.
   *
   * @since 1.0.0
   *
   * @param ClientInput $data the input data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return ClientOutput the processed output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ClientOutput
  {
    /** @var ClientInput $data */
    $name = $data->name ?? '';

    // Convert DTO to Command
    $command = new RegisterClientCommand(
      name: $name,
      redirectUris: array_map(fn (string $uri) => new RedirectUri($uri), $data->redirectUris),
      grantTypes: GrantTypes::fromArray($data->grantTypes),
      scopes: Scopes::fromArray($data->scopes),
    );

    /**
     * Command result.
     *
     * @var RegisterClientResult $result
     */
    $result = $this->commandBus->dispatch(command: $command);

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
  // #endregion
}
