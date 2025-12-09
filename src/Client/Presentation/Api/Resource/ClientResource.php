<?php

declare(strict_types=1);

namespace Client\Presentation\Api\Resource;

use ApiPlatform\Metadata\{
  ApiResource,
  Get,
  GetCollection,
  Post,
  Patch,
  Delete
};
use Client\Presentation\Api\Dto\ClientInput;
use Client\Presentation\Api\Dto\ClientOutput;
use Client\Presentation\Api\Processor\{
  RegisterClientProcessor,
  UpdateClientProcessor,
  RegenerateSecretProcessor,
  ActivateClientProcessor,
  DeactivateClientProcessor,
  DeleteClientProcessor
};
use Client\Presentation\Api\Provider\{GetClientProvider, ListClientsProvider};
use Client\Presentation\Api\Serialization\ClientSerializationGroup;

/**
 * Resource ClientResource
 * @final
 *
 * API Platform resource for Client management.
 *
 * @category Resource
 * @package Client\Presentation\Api\Resource
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Client',
  description: 'OAuth2 client application management. Clients are applications that can request access tokens.',
  operations: [
    new Post(
      name: 'create',
      description: 'Register a new OAuth2 client application. The client secret is shown only once.',
      uriTemplate: '/clients',
      input: ClientInput::class,
      output: ClientOutput::class,
      processor: RegisterClientProcessor::class,
      normalizationContext: ['groups' => [ClientSerializationGroup::READ, ClientSerializationGroup::SECRET]],
      denormalizationContext: ['groups' => [ClientSerializationGroup::WRITE]]
    ),
    new Get(
      name: 'get',
      description: 'Get details of a specific OAuth2 client. Secret is not included.',
      uriTemplate: '/clients/{id}',
      input: false,
      output: ClientOutput::class,
      provider: GetClientProvider::class,
      normalizationContext: ['groups' => [ClientSerializationGroup::READ]]
    ),
    new GetCollection(
      name: 'list',
      description: 'Returns a paginated list of all OAuth2 clients. Requires ROLE_ADMIN.',
      uriTemplate: '/clients',
      input: false,
      output: ClientOutput::class,
      provider: ListClientsProvider::class,
      normalizationContext: ['groups' => [ClientSerializationGroup::READ]]
    ),
    new Patch(
      name: 'update',
      description: 'Updates name, redirect URIs, grant types, or scopes of an existing client.',
      uriTemplate: '/clients/{id}',
      input: ClientInput::class,
      output: ClientOutput::class,
      processor: UpdateClientProcessor::class,
      normalizationContext: ['groups' => [ClientSerializationGroup::READ]],
      denormalizationContext: ['groups' => [ClientSerializationGroup::UPDATE]]
    ),
    new Post(
      name: 'regenerate-secret',
      description: 'Generates a new secret for the client. Store the new secret securely.',
      uriTemplate: '/clients/{id}/regenerate-secret',
      input: false,
      output: ClientOutput::class,
      processor: RegenerateSecretProcessor::class,
      normalizationContext: ['groups' => [ClientSerializationGroup::READ, ClientSerializationGroup::SECRET]]
    ),
    new Post(
      name: 'activate',
      description: 'Activates a previously deactivated client.',
      uriTemplate: '/clients/{id}/activate',
      input: false,
      output: ClientOutput::class,
      processor: ActivateClientProcessor::class,
      normalizationContext: ['groups' => [ClientSerializationGroup::READ]]
    ),
    new Post(
      name: 'deactivate',
      description: 'Deactivates a client, preventing it from requesting new tokens.',
      uriTemplate: '/clients/{id}/deactivate',
      input: false,
      output: ClientOutput::class,
      processor: DeactivateClientProcessor::class,
      normalizationContext: ['groups' => [ClientSerializationGroup::READ]]
    ),
    new Delete(
      name: 'delete',
      description: 'Permanently deletes an OAuth2 client. This action cannot be undone.',
      uriTemplate: '/clients/{id}',
      input: false,
      output: false,
      processor: DeleteClientProcessor::class
    )
  ]
)]
final class ClientResource
{
}
