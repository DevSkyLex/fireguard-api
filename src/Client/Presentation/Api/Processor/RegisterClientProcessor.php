<?php

declare(strict_types=1);

namespace Client\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Client\Application\UseCase\Command\RegisterClient\{RegisterClientCommand, RegisterClientResult};
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\ValueObject\GrantTypes;
use Shared\Domain\ValueObject\RedirectUri;
use Shared\Domain\ValueObject\Scopes;
use Client\Presentation\Api\Resource\ClientResource;

/**
 * Processor RegisterClientProcessor
 * @final
 *
 * API Platform processor for client registration.
 *
 * @category Processor
 * @package Client\Infrastructure\Adapter\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @implements ProcessorInterface<ClientResource, ClientResource>
 */
final readonly class RegisterClientProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the RegisterClientProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus The command bus.
   */
  public function __construct(
    private readonly CommandBusPort $commandBus
  ) {}
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
   * @param ClientResource $data The input data (ClientResource).
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return ClientResource The processed resource.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ClientResource
  {
    // Convert DTO to Command
    $command = new RegisterClientCommand(
      name: $data->name,
      redirectUris: array_map(fn(string $uri) => new RedirectUri($uri), $data->redirectUris),
      grantTypes: GrantTypes::fromArray($data->grantTypes),
      scopes: Scopes::fromArray($data->scopes)
    );

    // Dispatch command
    /** @var RegisterClientResult $result */
    $result = $this->commandBus->dispatch($command);

    // Update DTO with result
    $data->id = $result->clientId;
    $data->secret = $result->clientSecret;
    // We could fetch the full entity to get createdAt, but for now we can just set it to current time
    // or leave it null if not strictly required in the response immediately.
    // Let's set it for completeness.
    $data->createdAt = date(format: 'Y-m-d H:i:s');

    return $data;
  }
  //#endregion
}
