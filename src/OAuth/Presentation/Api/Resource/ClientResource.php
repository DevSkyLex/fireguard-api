<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Resource;

use ApiPlatform\Metadata\{
  ApiResource,
  Delete,
  Get,
  GetCollection,
  Patch,
  Post
};
use ApiPlatform\OpenApi\Model\{
  Operation,
  Parameter,
  Response
};
use ArrayObject;
use OAuth\Presentation\Api\Dto\Input\Client\ClientInput;
use OAuth\Presentation\Api\Dto\Output\Client\ClientOutput;
use OAuth\Presentation\Api\Operation\ClientOperations;
use OAuth\Presentation\Api\Processor\Client\{
  ActivateClientProcessor,
  DeactivateClientProcessor,
  DeleteClientProcessor,
  RegenerateSecretProcessor,
  RegisterClientProcessor,
  UpdateClientProcessor
};
use OAuth\Presentation\Api\Provider\Client\{GetClientProvider, ListClientsProvider};
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource ClientResource.
 *
 * @category Resource
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Client',
  description: 'OAuth2 client application management. Clients are applications that can request access tokens.',
  operations: [
    new Post(
      name: ClientOperations::CREATE,
      description: 'Register a new OAuth2 client application. The client secret is shown only once.',
      uriTemplate: '/clients',
      input: ClientInput::class,
      output: ClientOutput::class,
      processor: RegisterClientProcessor::class,
      normalizationContext: ['groups' => [OAuthSerializationGroup::CLIENT_READ, OAuthSerializationGroup::CLIENT_SECRET]],
      denormalizationContext: ['groups' => [OAuthSerializationGroup::CLIENT_WRITE]],
      security: "is_granted('clients.create')",
      openapi: new Operation(
        tags: ['Client Management'],
        summary: 'Register Client',
        description: 'Register a new OAuth2 client application. The response includes the client secret which must be stored securely as it will never be shown again.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_CREATED => new Response(
            description: 'Client registered successfully',
            links: new ArrayObject([
              'GetClient' => [
                'operationId' => 'get',
                'description' => 'Get client details',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
              'UpdateClient' => [
                'operationId' => 'update',
                'description' => 'Update client details',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid input data',
          ),
        ],
      ),
    ),
    new Get(
      name: ClientOperations::GET,
      description: 'Get details of a specific OAuth2 client. Secret is not included.',
      uriTemplate: '/clients/{id}',
      input: false,
      output: ClientOutput::class,
      provider: GetClientProvider::class,
      normalizationContext: ['groups' => [OAuthSerializationGroup::CLIENT_READ]],
      security: "is_granted('clients.read')",
      openapi: new Operation(
        tags: ['Client Management'],
        summary: 'Get Client Details',
        description: 'Retrieve detailed information about a specific OAuth2 client app.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Client details retrieved successfully',
            links: new ArrayObject([
              'UpdateClient' => [
                'operationId' => 'update',
                'description' => 'Update this client',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
              'RegenerateSecret' => [
                'operationId' => 'regenerate-secret',
                'description' => 'Regenerate client secret',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
              'DeleteClient' => [
                'operationId' => 'delete',
                'description' => 'Delete this client',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Client not found',
          ),
        ],
      ),
    ),
    new GetCollection(
      name: ClientOperations::LIST,
      description: 'Returns a paginated list of all OAuth2 clients. Requires clients.read permission.',
      uriTemplate: '/clients',
      input: false,
      output: ClientOutput::class,
      provider: ListClientsProvider::class,
      normalizationContext: ['groups' => [OAuthSerializationGroup::CLIENT_READ]],
      security: "is_granted('clients.read')",
      openapi: new Operation(
        tags: ['Client Management'],
        summary: 'List Clients',
        description: 'Retrieve a paginated list of all registered OAuth2 clients. Supports filtering by name and active status. Requires clients.read permission.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(
            name: 'name',
            in: 'query',
            required: false,
            description: 'Filter clients by name (partial match)',
            schema: ['type' => 'string'],
          ),
          new Parameter(
            name: 'isActive',
            in: 'query',
            required: false,
            description: 'Filter clients by active status',
            schema: ['type' => 'boolean'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'List of clients retrieved successfully',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions (requires clients.read)',
          ),
        ],
      ),
    ),
    new Patch(
      name: ClientOperations::UPDATE,
      description: 'Updates name, redirect URIs, grant types, or scopes of an existing client.',
      uriTemplate: '/clients/{id}',
      input: ClientInput::class,
      output: ClientOutput::class,
      processor: UpdateClientProcessor::class,
      normalizationContext: ['groups' => [OAuthSerializationGroup::CLIENT_READ]],
      denormalizationContext: ['groups' => [OAuthSerializationGroup::CLIENT_UPDATE]],
      security: "is_granted('clients.update')",
      openapi: new Operation(
        tags: ['Client Management'],
        summary: 'Update Client',
        description: 'Modify settings of an existing client application.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Client updated successfully',
            links: new ArrayObject([
              'GetClient' => [
                'operationId' => 'get',
                'description' => 'Get updated client details',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Client not found',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid input data',
          ),
        ],
      ),
    ),
    new Post(
      name: ClientOperations::REGENERATE_SECRET,
      description: 'Generates a new secret for the client. Store the new secret securely.',
      uriTemplate: '/clients/{id}/regenerate-secret',
      input: false,
      output: ClientOutput::class,
      processor: RegenerateSecretProcessor::class,
      normalizationContext: ['groups' => [OAuthSerializationGroup::CLIENT_READ, OAuthSerializationGroup::CLIENT_SECRET]],
      security: "is_granted('clients.update')",
      openapi: new Operation(
        tags: ['Client Management'],
        summary: 'Regenerate Secret',
        description: 'Invalidate the current client secret and generate a new one. The new secret is verified in the response and must be saved immediately.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Secret regenerated successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Client not found',
          ),
        ],
      ),
    ),
    new Post(
      name: ClientOperations::ACTIVATE,
      description: 'Activates a previously deactivated client.',
      uriTemplate: '/clients/{id}/activate',
      input: false,
      output: ClientOutput::class,
      status: HttpResponse::HTTP_OK,
      processor: ActivateClientProcessor::class,
      normalizationContext: ['groups' => [OAuthSerializationGroup::CLIENT_READ]],
      security: "is_granted('clients.update')",
      openapi: new Operation(
        tags: ['Client Management'],
        summary: 'Activate Client',
        description: 'Enable a deactivated client application, allowing it to request tokens again.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Client activated successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Client not found',
          ),
        ],
      ),
    ),
    new Post(
      name: ClientOperations::DEACTIVATE,
      description: 'Deactivates a client, preventing it from requesting new tokens.',
      uriTemplate: '/clients/{id}/deactivate',
      input: false,
      output: ClientOutput::class,
      status: HttpResponse::HTTP_OK,
      processor: DeactivateClientProcessor::class,
      normalizationContext: ['groups' => [OAuthSerializationGroup::CLIENT_READ]],
      security: "is_granted('clients.update')",
      openapi: new Operation(
        tags: ['Client Management'],
        summary: 'Deactivate Client',
        description: 'Disable a client application. It will no longer be able to request tokens.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Client deactivated successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Client not found',
          ),
        ],
      ),
    ),
    new Delete(
      name: ClientOperations::DELETE,
      description: 'Permanently deletes an OAuth2 client. This action cannot be undone.',
      uriTemplate: '/clients/{id}',
      input: false,
      output: false,
      processor: DeleteClientProcessor::class,
      security: "is_granted('clients.delete')",
      openapi: new Operation(
        tags: ['Client Management'],
        summary: 'Delete Client',
        description: 'Permanently remove a client application and all its associated tokens/consents.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(
            description: 'Client deleted successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Client not found',
          ),
        ],
      ),
    ),
  ],
)]
final class ClientResource
{
}
