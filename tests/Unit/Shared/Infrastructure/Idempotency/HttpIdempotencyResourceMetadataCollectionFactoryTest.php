<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use ApiPlatform\Metadata\{ApiResource, Get, HeaderParameter, HttpOperation, Post};
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\OpenApi\Model\{Example, MediaType, Operation as OpenApiOperation, Parameter, Response};
use ArrayObject;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;
use Shared\Application\Service\Idempotency\HttpIdempotencyOperationRegistry;
use Shared\Infrastructure\Idempotency\HttpIdempotencyResourceMetadataCollectionFactory;

/**
 * Class HttpIdempotencyResourceMetadataCollectionFactoryTest
 *
 * Verifies declared GET mutation coverage and staged header documentation without framework validation bypassing owner gates.
 *
 * @category Factory Tests
 */
#[CoversClass(HttpIdempotencyResourceMetadataCollectionFactory::class)]
final class HttpIdempotencyResourceMetadataCollectionFactoryTest extends TestCase
{
  #[Test]
  public function expiredConflictDocumentationMakesCompletionOptionalAndPreservesOwnerResponses(): void
  {
    $success = new Response(description: 'Owner success');
    $ownerMedia = new MediaType(schema: new ArrayObject(['type' => 'object']));
    $conflict = new Response(description: 'Owner conflict', content: new ArrayObject(['application/json' => $ownerMedia]));
    $inner = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
    $inner->method('create')->willReturn(new ResourceMetadataCollection(self::class, [new ApiResource(operations: ['stateful_get' => new Get(name: 'stateful_get', openapi: new OpenApiOperation(responses: [200 => $success, 409 => $conflict]))])]));
    $metadata = new HttpIdempotencyResourceMetadataCollectionFactory($inner, new HttpIdempotencyOperationRegistry([new MetadataTestContribution()]), true)->create(self::class);
    $http = $metadata->getOperation('stateful_get');
    self::assertInstanceOf(HttpOperation::class, $http);
    $documentation = $http->getOpenapi();
    self::assertInstanceOf(OpenApiOperation::class, $documentation);
    $responses = $documentation->getResponses();
    self::assertIsArray($responses);
    self::assertSame($success, $responses[200]);
    $documentedConflict = $responses[409];
    self::assertInstanceOf(Response::class, $documentedConflict);
    self::assertSame('Owner conflict', $documentedConflict->getDescription());
    $content = $documentedConflict->getContent();
    self::assertInstanceOf(ArrayObject::class, $content);
    self::assertSame($ownerMedia, $content['application/json']);
    $problem = $content['application/problem+json'];
    self::assertInstanceOf(MediaType::class, $problem);
    $schema = $problem->getSchema();
    self::assertInstanceOf(ArrayObject::class, $schema);
    $required = $schema['required'];
    self::assertIsArray($required);
    self::assertNotContains('operationCompleted', $required);
    $properties = $schema['properties'];
    self::assertIsArray($properties);
    $completion = $properties['operationCompleted'];
    self::assertIsArray($completion);
    self::assertSame('boolean', $completion['type']);
    $examples = $problem->getExamples();
    self::assertInstanceOf(ArrayObject::class, $examples);
    $expired = $examples['idempotencyReplayExpired'];
    $generic = $examples['idempotencyConflict'];
    self::assertInstanceOf(Example::class, $expired);
    self::assertInstanceOf(Example::class, $generic);
    $expiredValue = $expired->getValue();
    $genericValue = $generic->getValue();
    self::assertIsArray($expiredValue);
    self::assertIsArray($genericValue);
    self::assertTrue($expiredValue['operationCompleted']);
    self::assertArrayNotHasKey('operationCompleted', $genericValue);
  }

  #[Test]
  public function explicitlyDeclaredGetMutationsReceiveAStagedHeaderContract(): void
  {
    $metadata = new ResourceMetadataCollection(self::class, [new ApiResource(operations: ['stateful_get' => new Get(name: 'stateful_get')])]);
    $inner = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
    $inner->method('create')->willReturn($metadata);
    $registry = new HttpIdempotencyOperationRegistry([new MetadataTestContribution()]);
    foreach ([false, true] as $required) {
      $operation = new HttpIdempotencyResourceMetadataCollectionFactory($inner, $registry, $required)->create(self::class)->getOperation('stateful_get');
      $header = $operation->getParameters()?->get('Idempotency-Key', HeaderParameter::class);
      self::assertInstanceOf(HeaderParameter::class, $header);
      self::assertFalse($header->getRequired());
      self::assertSame([], $header->getConstraints());
      $openApi = $header->getOpenApi();
      self::assertInstanceOf(Parameter::class, $openApi);
      self::assertSame($required, $openApi->getRequired());
      $extras = $operation->getExtraProperties();
      self::assertIsArray($extras);
      self::assertArrayHasKey('http_idempotency', $extras);
      $ownership = $extras['http_idempotency'];
      self::assertIsArray($ownership);
      self::assertArrayHasKey('database', $ownership);
      self::assertSame('main', $ownership['database']);
    }
    self::assertNull($metadata->getOperation('stateful_get')->getParameters());
  }

  #[Test]
  public function strictCoverageRejectsUnclassifiedWrites(): void
  {
    $inner = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
    $inner->method('create')->willReturn(new ResourceMetadataCollection(self::class, [new ApiResource(operations: ['unowned' => new Post(name: 'unowned')])]));
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('no explicit idempotency owner');
    new HttpIdempotencyResourceMetadataCollectionFactory($inner, new HttpIdempotencyOperationRegistry([]), true)->create(self::class);
  }

  #[Test]
  public function onlyExplicitAuditedReadOnlyPostsAreExempt(): void
  {
    $inner = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
    $inner->method('create')->willReturn(new ResourceMetadataCollection(self::class, [new ApiResource(operations: ['introspect_token' => new Post(name: 'introspect_token')])]));
    $metadata = new HttpIdempotencyResourceMetadataCollectionFactory($inner, new HttpIdempotencyOperationRegistry([]), true, ['introspect_token'])->create(self::class);
    self::assertNull($metadata->getOperation('introspect_token')->getParameters());
  }

  #[Test]
  public function nativeVerifiedWritesAreDeclaredSeparatelyFromReadOnlyExemptions(): void
  {
    $inner = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
    $inner->method('create')->willReturn(new ResourceMetadataCollection(self::class, [new ApiResource(operations: ['authorize' => new Post(name: 'authorize')])]));
    $metadata = new HttpIdempotencyResourceMetadataCollectionFactory($inner, new HttpIdempotencyOperationRegistry([]), true, nativeProofOperations: ['authorize'])->create(self::class);
    $extras = $metadata->getOperation('authorize')->getExtraProperties();
    self::assertIsArray($extras);
    self::assertArrayHasKey('http_idempotency_native_proof', $extras);
    self::assertTrue($extras['http_idempotency_native_proof']);
    self::assertNull($metadata->getOperation('authorize')->getParameters());
  }

  #[Test]
  public function protocolConditionalHeaderDocumentationDoesNotAlterTheRuntimeDescriptor(): void
  {
    $inner = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
    $inner->method('create')->willReturn(new ResourceMetadataCollection(self::class, [new ApiResource(operations: ['stateful_get' => new Get(name: 'stateful_get')])]));
    $registry = new HttpIdempotencyOperationRegistry([new MetadataTestContribution()]);
    $metadata = new HttpIdempotencyResourceMetadataCollectionFactory($inner, $registry, true, conditionalKeyDescriptions: ['stateful_get' => 'Required for the explicit header grant; verified native grants derive their key.'])->create(self::class);
    $header = $metadata->getOperation('stateful_get')->getParameters()?->get('Idempotency-Key', HeaderParameter::class);
    self::assertInstanceOf(HeaderParameter::class, $header);
    $documentation = $header->getOpenApi();
    self::assertInstanceOf(Parameter::class, $documentation);
    self::assertFalse($documentation->getRequired());
    self::assertTrue($registry->find('stateful_get')?->requiredKey);
  }
}

final readonly class MetadataTestContribution implements HttpIdempotencyOperationContributionPort
{
  public static function definitions(): array
  {
    return [new HttpIdempotencyOperation('stateful_get', 'main', 'bus_owner', MetadataTestCommand::class, MetadataTestResult::class, requiredKey: true)];
  }
}

final readonly class MetadataTestCommand implements CommandMessage
{
}

final readonly class MetadataTestResult implements ResultMessage
{
}
