<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\Operation;
use Auth\Presentation\Api\Dto\Input\Federation\{FederatedCompleteInput, FederatedStartInput};
use Auth\Presentation\Api\Dto\Output\Auth\LoginOutput;
use Auth\Presentation\Api\Dto\Output\Federation\{FederatedConnectionsOutput, FederatedProviderOutput, FederatedStartOutput};
use Auth\Presentation\Api\Operation\FederatedAuthOperations;
use Auth\Presentation\Api\Processor\Federation\{FederatedCompleteProcessor, FederatedDisconnectProcessor, FederatedStartProcessor};
use Auth\Presentation\Api\Provider\Federation\{FederatedConnectionsProvider, FederatedProvidersProvider};
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource FederatedAuthResource.
 *
 * Declares the Google and Microsoft sign-in, callback and connection API.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'FederatedAuth',
  routePrefix: '/auth/federated',
  operations: [
    new GetCollection(
      name: FederatedAuthOperations::PROVIDERS,
      uriTemplate: '/providers',
      output: FederatedProviderOutput::class,
      provider: FederatedProvidersProvider::class,
      paginationEnabled: false,
      openapi: new Operation(tags: ['Authentication'], summary: 'List enabled sign-in providers'),
    ),
    new Post(
      name: FederatedAuthOperations::LOGIN_START,
      uriTemplate: '/{provider}/start',
      requirements: ['provider' => 'google|microsoft'],
      status: Response::HTTP_OK,
      read: false,
      input: FederatedStartInput::class,
      output: FederatedStartOutput::class,
      processor: FederatedStartProcessor::class,
      openapi: new Operation(tags: ['Authentication'], summary: 'Start federated sign-in'),
    ),
    new Post(
      name: FederatedAuthOperations::LOGIN_COMPLETE,
      uriTemplate: '/{provider}/complete',
      requirements: ['provider' => 'google|microsoft'],
      status: Response::HTTP_OK,
      read: false,
      input: FederatedCompleteInput::class,
      output: LoginOutput::class,
      processor: FederatedCompleteProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      openapi: new Operation(tags: ['Authentication'], summary: 'Complete federated sign-in'),
    ),
    new Get(
      name: FederatedAuthOperations::CONNECTIONS,
      uriTemplate: '/connections',
      output: FederatedConnectionsOutput::class,
      provider: FederatedConnectionsProvider::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(tags: ['Authentication'], summary: 'List sign-in methods'),
    ),
    new Post(
      name: FederatedAuthOperations::LINK_START,
      uriTemplate: '/connections/{provider}/start',
      requirements: ['provider' => 'google|microsoft'],
      status: Response::HTTP_OK,
      read: false,
      input: FederatedStartInput::class,
      output: FederatedStartOutput::class,
      processor: FederatedStartProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(tags: ['Authentication'], summary: 'Start linking a sign-in provider'),
    ),
    new Post(
      name: FederatedAuthOperations::LINK_COMPLETE,
      uriTemplate: '/connections/{provider}/complete',
      requirements: ['provider' => 'google|microsoft'],
      status: Response::HTTP_OK,
      read: false,
      input: FederatedCompleteInput::class,
      output: FederatedConnectionsOutput::class,
      processor: FederatedCompleteProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(tags: ['Authentication'], summary: 'Complete linking a sign-in provider'),
    ),
    new Delete(
      name: FederatedAuthOperations::DISCONNECT,
      uriTemplate: '/connections/{provider}',
      requirements: ['provider' => 'google|microsoft'],
      status: Response::HTTP_OK,
      read: false,
      input: false,
      output: FederatedConnectionsOutput::class,
      processor: FederatedDisconnectProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(tags: ['Authentication'], summary: 'Disconnect a sign-in provider'),
    ),
  ],
)]
final class FederatedAuthResource
{
}
