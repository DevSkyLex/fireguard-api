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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Client',
  operations: [
    new Post(
      name: 'create',
      uriTemplate: '/clients',
      input: ClientInput::class,
      output: ClientOutput::class,
      processor: RegisterClientProcessor::class,
      normalizationContext: ['groups' => [ClientSerializationGroup::READ, ClientSerializationGroup::SECRET]],
      denormalizationContext: ['groups' => [ClientSerializationGroup::WRITE]]
    ),
    new Get(
      name: 'get',
      uriTemplate: '/clients/{id}',
      input: false,
      output: ClientOutput::class,
      provider: GetClientProvider::class,
      normalizationContext: ['groups' => [ClientSerializationGroup::READ]]
    ),
    new GetCollection(
      name: 'list',
      uriTemplate: '/clients',
      input: false,
      output: ClientOutput::class,
      provider: ListClientsProvider::class,
      normalizationContext: ['groups' => [ClientSerializationGroup::READ]]
    ),
    new Patch(
      name: 'update',
      uriTemplate: '/clients/{id}',
      input: ClientInput::class,
      output: ClientOutput::class,
      processor: UpdateClientProcessor::class,
      normalizationContext: ['groups' => [ClientSerializationGroup::READ]],
      denormalizationContext: ['groups' => [ClientSerializationGroup::UPDATE]]
    ),
    new Post(
      name: 'regenerate-secret',
      uriTemplate: '/clients/{id}/regenerate-secret',
      input: false,
      output: ClientOutput::class,
      processor: RegenerateSecretProcessor::class,
      normalizationContext: ['groups' => [ClientSerializationGroup::READ, ClientSerializationGroup::SECRET]]
    ),
    new Post(
      name: 'activate',
      uriTemplate: '/clients/{id}/activate',
      input: false,
      output: ClientOutput::class,
      processor: ActivateClientProcessor::class,
      normalizationContext: ['groups' => [ClientSerializationGroup::READ]]
    ),
    new Post(
      name: 'deactivate',
      uriTemplate: '/clients/{id}/deactivate',
      input: false,
      output: ClientOutput::class,
      processor: DeactivateClientProcessor::class,
      normalizationContext: ['groups' => [ClientSerializationGroup::READ]]
    ),
    new Delete(
      name: 'delete',
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
