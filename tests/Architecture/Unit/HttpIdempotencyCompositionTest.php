<?php

declare(strict_types=1);

namespace Tests\Architecture\Unit;

use ApiPlatform\Metadata\HeaderParameter;
use ApiPlatform\Metadata\Resource\Factory\{ResourceMetadataCollectionFactoryInterface, ResourceNameCollectionFactoryInterface};
use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\{Parameter, PathItem, Response as OpenApiResponse};
use ArrayObject;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Shared\Application\Message\CommandMessage;
use Shared\Application\Service\Idempotency\HttpIdempotencyOperationRegistry;
use Shared\Infrastructure\Idempotency\HttpIdempotencyPolicyRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use User\Presentation\Api\Resource\UserResource;

use function array_keys;
use function count;
use function in_array;
use function iterator_to_array;
use function sort;

/**
 * Class HttpIdempotencyCompositionTest
 *
 * Audits actual API metadata and selected owner services before mandatory transport contracts can be published.
 *
 * @category Architecture Tests
 */
final class HttpIdempotencyCompositionTest extends KernelTestCase
{
  // #region Constants
  /**
   * Constant STATEFUL_GET_OPERATIONS
   *
   * Independent inventory of GET routes that export material or synchronize persisted state.
   *
   * @var list<string>
   */
  private const array STATEFUL_GET_OPERATIONS = [
    'equipment_export',
    'equipment_export_report',
    'equipment_export_labels',
    'facility_export',
    'audit_events_export',
    'exportOrganizationSafetyRegister',
    'exportFacilitySafetyRegister',
    'inspection_export',
    'inspection_export_non_conformities',
    'inspection_export_report',
    'inspection_export_non_conformities_report',
    'intervention_export',
    'intervention_export_report',
    'maintenance_schedule_export',
    'listOrganizationInvitations',
    'getCalendarFeedIcs',
    'getOrganizationOnboarding',
  ];
  // #endregion

  // #region Methods
  /**
   * Method everyMutationHasExplicitOwnershipAndAReplayGate
   *
   * Uses the production module composition while test transport compatibility remains enabled.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function everyMutationHasExplicitOwnershipAndAReplayGate(): void
  {
    self::bootKernel();
    $container = self::getContainer();
    $names = $container->get('api_platform.metadata.resource.name_collection_factory');
    self::assertInstanceOf(ResourceNameCollectionFactoryInterface::class, $names);
    $metadata = $container->get('api_platform.metadata.resource.metadata_collection_factory');
    self::assertInstanceOf(ResourceMetadataCollectionFactoryInterface::class, $metadata);
    $operations = $container->get(HttpIdempotencyOperationRegistry::class);
    self::assertInstanceOf(HttpIdempotencyOperationRegistry::class, $operations);
    $handlers = $container->get('messenger.bus.default.messenger.handlers_locator');
    self::assertInstanceOf(HandlersLocatorInterface::class, $handlers);
    $policies = $container->get(HttpIdempotencyPolicyRegistry::class);
    self::assertInstanceOf(HttpIdempotencyPolicyRegistry::class, $policies);
    $readOnly = $container->getParameter('http_idempotency.read_only_operations');
    self::assertIsArray($readOnly);
    $nativeProof = $container->getParameter('http_idempotency.native_proof_operations');
    self::assertIsArray($nativeProof);
    $conditionalKeys = $container->getParameter('http_idempotency.conditional_key_descriptions');
    self::assertIsArray($conditionalKeys);
    $missingOwners = [];
    $missingGates = [];
    $missingHandlers = [];
    $writes = 0;
    $declaredNames = [];
    $declaredStatefulGets = [];
    foreach ($names->create() as $resourceClass) {
      foreach ($metadata->create($resourceClass) as $resource) {
        foreach ($resource->getOperations() ?? [] as $name => $http) {
          $declaredNames[$http->getName() ?? $name] = true;
          $owner = $operations->find($http->getName() ?? $name);
          if (null !== $owner) {
            if ('GET' === $http->getMethod()) {
              $declaredStatefulGets[$owner->name] = true;
            }
            $header = $http->getParameters()?->get('Idempotency-Key', HeaderParameter::class);
            self::assertInstanceOf(HeaderParameter::class, $header, $owner->name);
            self::assertSame(['type' => 'string', 'minLength' => 1, 'maxLength' => 255, 'pattern' => '^[!-~]+$'], $header->getSchema(), $owner->name);
            $openApi = $header->getOpenApi();
            self::assertInstanceOf(Parameter::class, $openApi, $owner->name);
            self::assertSame(!isset($conditionalKeys[$owner->name]), $openApi->getRequired(), $owner->name);
          }
          if (!in_array($http->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) || in_array($name, $readOnly, true) || in_array($name, $nativeProof, true)) {
            continue;
          }
          ++$writes;
          if (null === $owner) {
            $missingOwners[] = $name;

            continue;
          }
          if (null === $policies->ordinary($owner->name) && null === $policies->sensitive($owner->primaryCommand)) {
            $missingGates[] = $name;
          }
        }
      }
    }
    foreach ($operations->all() as $owner) {
      $command = new ReflectionClass($owner->primaryCommand)->newInstanceWithoutConstructor();
      self::assertInstanceOf(CommandMessage::class, $command);
      $handlerCount = count(iterator_to_array($handlers->getHandlers(new Envelope($command))));
      if (1 !== $handlerCount) {
        $missingHandlers[$owner->primaryCommand] = $handlerCount;
      }
      self::assertArrayHasKey($owner->name, $declaredNames, 'Owner manifest has no actual API operation');
      if (null === $policies->ordinary($owner->name) && null === $policies->sensitive($owner->primaryCommand) && !in_array($owner->name, $missingGates, true)) {
        $missingGates[] = $owner->name;
      }
    }
    sort($missingOwners);
    sort($missingGates);
    self::assertGreaterThan(100, $writes);
    $problems = [];
    if ([] !== $missingHandlers) {
      $problems['primary_handler_counts'] = $missingHandlers;
    }
    foreach (self::STATEFUL_GET_OPERATIONS as $name) {
      if (null === $operations->find($name)) {
        $problems['uncovered_stateful_gets'][] = $name;
      }
    }
    if ([] !== $missingOwners) {
      $problems['uncovered_mutations'] = $missingOwners;
    }
    if ([] !== $missingGates) {
      $problems['missing_replay_gates'] = $missingGates;
    }
    self::assertSame([], $problems, 'Mandatory HTTP composition is incomplete');
    $expectedStatefulGets = self::STATEFUL_GET_OPERATIONS;
    $actualStatefulGets = array_keys($declaredStatefulGets);
    sort($expectedStatefulGets);
    sort($actualStatefulGets);
    self::assertSame($expectedStatefulGets, $actualStatefulGets, 'Every declared GET mutation must appear in the independent inventory.');
  }

  /**
   * Method mandatoryMetadataPublishesTheHeaderBeforeExecution
   *
   * Checks effective owner metadata without enabling stricter validation for unrelated legacy fixtures.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function mandatoryMetadataPublishesTheHeaderBeforeExecution(): void
  {
    self::bootKernel();
    $container = self::getContainer();
    $inner = $container->get('api_platform.metadata.resource.metadata_collection_factory');
    self::assertInstanceOf(ResourceMetadataCollectionFactoryInterface::class, $inner);
    $operations = $container->get(HttpIdempotencyOperationRegistry::class);
    self::assertInstanceOf(HttpIdempotencyOperationRegistry::class, $operations);
    self::assertTrue($container->getParameter('http_idempotency.documentation_required'));
    self::assertFalse($container->getParameter('http_idempotency.required'));
    $factory = $inner;
    $headers = 0;
    foreach ($factory->create(UserResource::class) as $resource) {
      foreach ($resource->getOperations() ?? [] as $operation) {
        if (!in_array($operation->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
          continue;
        }
        $header = $operation->getParameters()?->get('Idempotency-Key', HeaderParameter::class);
        self::assertInstanceOf(HeaderParameter::class, $header);
        self::assertSame(['type' => 'string', 'minLength' => 1, 'maxLength' => 255, 'pattern' => '^[!-~]+$'], $header->getSchema());
        self::assertFalse($header->getRequired());
        $openApi = $header->getOpenApi();
        self::assertInstanceOf(Parameter::class, $openApi);
        self::assertTrue($openApi->getRequired());
        ++$headers;
      }
    }
    self::assertGreaterThan(0, $headers);
  }

  /**
   * Method complianceExportsPublishTheirActualPdfSuccessResponse
   *
   * Validates generated OpenAPI instead of inferring binary response status from output:false metadata.
   *
   * @return void
   */
  #[Test]
  public function complianceExportsPublishTheirActualPdfSuccessResponse(): void
  {
    self::bootKernel();
    $factory = self::getContainer()->get('api_platform.openapi.factory');
    self::assertInstanceOf(OpenApiFactoryInterface::class, $factory);
    $expected = ['exportOrganizationSafetyRegister', 'exportFacilitySafetyRegister'];
    $found = [];
    foreach ($factory()->getPaths()->getPaths() as $path) {
      self::assertInstanceOf(PathItem::class, $path);
      $operation = $path->getGet();
      if (null === $operation || !in_array($operation->getOperationId(), $expected, true)) {
        continue;
      }
      $responses = $operation->getResponses() ?? [];
      self::assertArrayHasKey(200, $responses);
      self::assertArrayNotHasKey(204, $responses);
      $response = $responses[200];
      self::assertInstanceOf(OpenApiResponse::class, $response);
      $content = $response->getContent();
      self::assertInstanceOf(ArrayObject::class, $content);
      self::assertSame(['schema' => ['type' => 'string', 'format' => 'binary']], $content['application/pdf']);
      $found[] = $operation->getOperationId();
    }
    sort($expected);
    sort($found);
    self::assertSame($expected, $found);
  }
  // #endregion
}
