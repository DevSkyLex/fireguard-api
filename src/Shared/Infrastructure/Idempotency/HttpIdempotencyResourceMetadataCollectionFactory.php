<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use ApiPlatform\Metadata\{HeaderParameter, HttpOperation, Parameters};
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\OpenApi\Model\{Example, MediaType, Operation as OpenApiOperation, Parameter, Reference, Response};
use ArrayObject;
use LogicException;
use Shared\Application\Service\Idempotency\HttpIdempotencyOperationRegistry;

use function in_array;
use function sprintf;

/**
 * Class HttpIdempotencyResourceMetadataCollectionFactory
 *
 * Publishes the declared mutation header contract and rejects uncovered writes when mandatory coverage is enabled.
 * Header validation remains at the authorized HTTP boundary so compatibility staging and protocol errors stay consistent.
 *
 * @category Factory
 */
final readonly class HttpIdempotencyResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param ResourceMetadataCollectionFactoryInterface $inner the framework metadata factory
   * @param HttpIdempotencyOperationRegistry $operations explicit owner operation catalog
   * @param bool $required whether complete mandatory coverage is enabled
   * @param list<string> $readOnlyOperations explicitly audited nonmutating POST operations
   * @param list<string> $nativeProofOperations explicitly audited owner-native mutations with a verified protocol receipt
   * @param array<string, string> $conditionalKeyDescriptions owner-declared documentation for protocol-conditionally required keys
   *
   * @return void
   */
  public function __construct(
    private ResourceMetadataCollectionFactoryInterface $inner,
    private HttpIdempotencyOperationRegistry $operations,
    private bool $required = false,
    private array $readOnlyOperations = [],
    private array $nativeProofOperations = [],
    private array $conditionalKeyDescriptions = [],
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create
   *
   * Includes explicitly declared stateful GET operations and preserves existing resource and query metadata.
   *
   * @access public
   *
   * @param string $resourceClass the framework resource class
   *
   * @return ResourceMetadataCollection the owner-annotated operation metadata
   */
  public function create(string $resourceClass): ResourceMetadataCollection
  {
    $collection = $this->inner->create($resourceClass);
    $resources = [];
    foreach ($collection as $resource) {
      $declaredOperations = $resource->getOperations() ?? new \ApiPlatform\Metadata\Operations();
      $httpOperations = clone $declaredOperations;
      foreach ($httpOperations as $name => $http) {
        $httpOperations->add($name, $this->withIdempotencyContract($name, $http));
      }
      $resources[] = $resource->withOperations($httpOperations);
    }

    return new ResourceMetadataCollection($resourceClass, $resources);
  }

  /**
   * Method withIdempotencyContract
   *
   * Preserves native protocol exceptions and decorates one explicitly owned mutation.
   *
   * @access private
   *
   * @param string $name the resource's operation key
   * @param HttpOperation $http the framework operation
   *
   * @return HttpOperation the validated and annotated operation
   */
  private function withIdempotencyContract(string $name, HttpOperation $http): HttpOperation
  {
    $operation = $this->operations->find($http->getName() ?? $name);
    if (null === $operation) {
      if (in_array($name, $this->nativeProofOperations, true)) {
        $extra = $http->getExtraProperties();
        $extra['http_idempotency_native_proof'] = true;

        return $http->withExtraProperties($extra);
      }
      if ($this->required && in_array($http->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !in_array($name, $this->readOnlyOperations, true)) {
        throw new LogicException(sprintf('HTTP mutation "%s" has no explicit idempotency owner.', $name));
      }

      return $http;
    }
    $declaredParameters = $http->getParameters() ?? new Parameters();
    $parameters = clone $declaredParameters;
    $conditional = $this->conditionalKeyDescriptions[$operation->name] ?? null;
    $description = $conditional ?? 'Opaque logical-operation key. Repeat the same key and request to obtain the original authorized outcome.';
    $schema = ['type' => 'string', 'minLength' => 1, 'maxLength' => 255, 'pattern' => '^[!-~]+$'];
    $parameters->add('Idempotency-Key', new HeaderParameter(
      key: 'Idempotency-Key',
      schema: $schema,
      openApi: new Parameter('Idempotency-Key', 'header', $description, null === $conditional && $this->required && $operation->requiredKey, schema: $schema),
      description: $description,
      required: false,
      constraints: [],
      castToArray: false,
      castToNativeType: false,
    ));
    $extra = $http->getExtraProperties();
    $extra['http_idempotency'] = ['database' => $operation->database, 'transactionMode' => $operation->transactionMode, 'secret' => $operation->secret];

    return $this->withConflictContract($http->withParameters($parameters)->withExtraProperties($extra));
  }

  /**
   * Method withConflictContract
   *
   * Preserves owner responses and documents acknowledgement only for authenticated matching completed receipts whose result expired.
   *
   * @access private
   *
   * @param HttpOperation $http declared owner HTTP operation
   *
   * @return HttpOperation augmented conflict documentation
   */
  private function withConflictContract(HttpOperation $http): HttpOperation
  {
    $declared = $http->getOpenapi();
    if (false === $declared) {
      return $http;
    }
    $documentation = $declared instanceof OpenApiOperation ? clone $declared : new OpenApiOperation();
    $schema = [
      'type' => 'object',
      'required' => ['type', 'title', 'status', 'code', 'detail'],
      'properties' => [
        'type' => ['type' => 'string'],
        'title' => ['type' => 'string'],
        'status' => ['type' => 'integer', 'enum' => [409]],
        'code' => ['type' => 'string'],
        'detail' => ['type' => 'string'],
        'operationCompleted' => [
          'type' => 'boolean',
          'description' => 'True only for idempotency_replay_expired after the completed receipt is authenticated, the canonical request matches and current owner access is verified. Other conflicts do not acknowledge completion.',
        ],
      ],
    ];
    $examples = new ArrayObject([
      'idempotencyConflict' => new Example(summary: 'Changed input with the original key', value: ['type' => '/errors/idempotency_conflict', 'title' => 'Idempotency request denied', 'status' => 409, 'code' => 'idempotency_conflict', 'detail' => 'The original operation cannot be safely repeated.']),
      'idempotencyReplayExpired' => new Example(summary: 'Authenticated matching operation already completed', value: ['type' => '/errors/idempotency_replay_expired', 'title' => 'Idempotency request denied', 'status' => 409, 'code' => 'idempotency_replay_expired', 'detail' => 'The original operation cannot be safely repeated.', 'operationCompleted' => true]),
    ]);
    $responses = $documentation->getResponses() ?? [];
    $existing = $responses[409] ?? null;
    if ($existing instanceof Reference) {
      $extended = $documentation->withExtensionProperty('x-idempotency-conflict-contract', ['schema' => $schema, 'examples' => $examples]);
      if (!$extended instanceof OpenApiOperation) {
        throw new LogicException('OpenAPI conflict extension did not preserve its operation.');
      }

      return $http->withOpenapi($extended);
    }
    $response = $existing instanceof Response ? clone $existing : new Response(description: 'Idempotency conflict, in-progress operation, uncertain outcome or expired original result.');
    $declaredContent = $response->getContent() ?? new ArrayObject();
    $content = clone $declaredContent;
    $existingMedia = $content['application/problem+json'] ?? null;
    if ($existingMedia instanceof MediaType) {
      $existingSchema = $existingMedia->getSchema();
      if (null !== $existingSchema) {
        $schema = ['anyOf' => [$existingSchema->getArrayCopy(), $schema]];
      }
      $declaredExamples = $existingMedia->getExamples() ?? new ArrayObject();
      $mergedExamples = clone $declaredExamples;
      foreach ($examples as $name => $example) {
        $mergedExamples[$name] = $example;
      }
      $media = $existingMedia->withSchema(new ArrayObject($schema))->withExamples($mergedExamples);
    } else {
      $media = new MediaType(schema: new ArrayObject($schema), examples: $examples);
    }
    $content['application/problem+json'] = $media;
    $responses[409] = $response->withContent($content);

    return $http->withOpenapi($documentation->withResponses($responses));
  }
  // #endregion
}
