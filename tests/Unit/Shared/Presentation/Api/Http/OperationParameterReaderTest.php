<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Http;

use ApiPlatform\Metadata\{Get, HeaderParameter, Parameters, QueryParameter};
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Http\OperationParameterReader;
use Symfony\Component\HttpFoundation\{HeaderBag, InputBag, Request};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Test OperationParameterReaderTest.
 *
 * @category Unit Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OperationParameterReaderTest extends TestCase
{
  // #region Methods
  /**
   * Method testParsedValuesOverrideRawFiltersWithoutLosingUnknownKeys.
   *
   * @since 1.0.0
   */
  public function testParsedValuesOverrideRawFiltersWithoutLosingUnknownKeys(): void
  {
    $page = new QueryParameter();
    $page->setValue(2);
    $flag = new QueryParameter();
    $flag->setValue(false);
    $operation = new Get(parameters: new Parameters(['page' => $page, 'overloaded' => $flag]));

    self::assertSame(
      ['page' => 2, 'overloaded' => false, 'legacy' => 'retained'],
      OperationParameterReader::filters($operation, ['filters' => ['page' => '2', 'overloaded' => 'false', 'legacy' => 'retained']]),
    );
  }

  /**
   * Method testMissingParametersRetainProviderFallbacks.
   *
   * @since 1.0.0
   */
  public function testMissingParametersRetainProviderFallbacks(): void
  {
    $operation = new Get(parameters: new Parameters(['limit' => new QueryParameter()]));

    self::assertSame([], OperationParameterReader::filters($operation, []));
    self::assertSame(['limit' => '10'], OperationParameterReader::filters($operation, ['filters' => ['limit' => '10']]));
  }

  /**
   * Method testBracketedParametersKeepTheirNestedShapeAndRequestIsNotMutated.
   *
   * @since 1.0.0
   */
  public function testBracketedParametersKeepTheirNestedShapeAndRequestIsNotMutated(): void
  {
    $order = new QueryParameter();
    $order->setValue('desc');
    $statuses = new QueryParameter();
    $statuses->setValue(['draft', 'planned']);
    $operation = new Get(parameters: new Parameters(['order[createdAt]' => $order, 'status[]' => $statuses]));
    $request = Request::create('/items?order[name]=asc&status=draft');

    $query = OperationParameterReader::query($operation, $request);

    self::assertSame(['name' => 'asc', 'createdAt' => 'desc'], $query->all('order'));
    self::assertSame(['draft', 'planned'], $query->all('status'));
    self::assertSame('draft', $request->query->get('status'));
    self::assertSame(['name' => 'asc'], $request->query->all('order'));
  }

  /**
   * Method testHeadersRemainSeparateAndKeepConditionalRequestSyntax.
   *
   * @since 1.0.0
   */
  public function testHeadersRemainSeparateAndKeepConditionalRequestSyntax(): void
  {
    $etag = new HeaderParameter();
    $etag->setValue('W/"revision-2", "revision-3"');
    $operation = new Get(parameters: new Parameters(['If-Match' => $etag]));
    $request = Request::create('/items', server: ['HTTP_IF_MATCH' => '"revision-1"', 'HTTP_ACCEPT' => 'application/ld+json']);

    $headers = OperationParameterReader::headers($operation, $request);

    self::assertSame('W/"revision-2", "revision-3"', $headers->get('If-Match'));
    self::assertSame('application/ld+json', $headers->get('Accept'));
    self::assertSame('"revision-1"', $request->headers->get('If-Match'));
    self::assertSame([], OperationParameterReader::filters($operation, []));
  }

  /**
   * Method filterReaders
   *
   * @access public
   *
   * @return iterable<string, array{Closure(Get, array<string, mixed>): array<string, mixed>}>
   */
  public static function filterReaders(): iterable
  {
    yield 'Audit' => [\Audit\Presentation\Api\Http\OperationParameterReader::filters(...)];
    yield 'Auth' => [\Auth\Presentation\Api\Http\OperationParameterReader::filters(...)];
    yield 'Authorization' => [\Authorization\Presentation\Api\Http\OperationParameterReader::filters(...)];
    yield 'Calendar' => [\Calendar\Presentation\Api\Http\OperationParameterReader::filters(...)];
    yield 'Equipment' => [\Equipment\Presentation\Api\Http\OperationParameterReader::filters(...)];
    yield 'Facility' => [\Facility\Presentation\Api\Http\OperationParameterReader::filters(...)];
    yield 'Inspection' => [\Inspection\Presentation\Api\Http\OperationParameterReader::filters(...)];
    yield 'Notification' => [\Notification\Presentation\Api\Http\OperationParameterReader::filters(...)];
    yield 'OAuth' => [\OAuth\Presentation\Api\Http\OperationParameterReader::filters(...)];
    yield 'Organization' => [\Organization\Presentation\Api\Http\OperationParameterReader::filters(...)];
    yield 'Session' => [\Session\Presentation\Api\Http\OperationParameterReader::filters(...)];
    yield 'Shared' => [OperationParameterReader::filters(...)];
    yield 'Tenant' => [\Tenant\Presentation\Api\Http\OperationParameterReader::filters(...)];
    yield 'TrustedDevice' => [\TrustedDevice\Presentation\Api\Http\OperationParameterReader::filters(...)];
    yield 'User' => [\User\Presentation\Api\Http\OperationParameterReader::filters(...)];
    yield 'Workload' => [\Workload\Presentation\Api\Http\OperationParameterReader::filters(...)];
  }

  /**
   * Method queryReaders
   *
   * @access public
   *
   * @return iterable<string, array{Closure(Get, Request|null): InputBag<bool|float|int|string>}>
   */
  public static function queryReaders(): iterable
  {
    yield 'Approval' => [\Approval\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'Assistant' => [\Assistant\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'Auth' => [\Auth\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'Compliance' => [\Compliance\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'Equipment' => [\Equipment\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'Facility' => [\Facility\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'Import' => [\Import\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'Inspection' => [\Inspection\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'Intervention' => [\Intervention\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'Maintenance' => [\Maintenance\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'Messaging' => [\Messaging\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'Notification' => [\Notification\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'OAuth' => [\OAuth\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'Organization' => [\Organization\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'Shared' => [OperationParameterReader::query(...)];
    yield 'User' => [\User\Presentation\Api\Http\OperationParameterReader::query(...)];
    yield 'Webhook' => [\Webhook\Presentation\Api\Http\OperationParameterReader::query(...)];
  }

  /**
   * Method headerReaders
   *
   * @access public
   *
   * @return iterable<string, array{Closure(Get, Request|null): HeaderBag}>
   */
  public static function headerReaders(): iterable
  {
    yield 'Auth' => [\Auth\Presentation\Api\Http\OperationParameterReader::headers(...)];
    yield 'TrustedDevice' => [\TrustedDevice\Presentation\Api\Http\OperationParameterReader::headers(...)];
    yield 'Shared' => [OperationParameterReader::headers(...)];
  }

  /**
   * Method testOwnerFiltersRetainFallbacksAndKeepHeadersSeparate
   *
   * @access public
   *
   * @param Closure(Get, array<string, mixed>): array<string, mixed> $reader the owner-local HTTP bridge
   *
   * @return void
   */
  #[DataProvider('filterReaders')]
  public function testOwnerFiltersRetainFallbacksAndKeepHeadersSeparate(Closure $reader): void
  {
    $status = new QueryParameter();
    $status->setValue(null);
    $enabled = new QueryParameter();
    $enabled->setValue(false);
    $header = new HeaderParameter();
    $header->setValue('header-only');
    $operation = new Get(parameters: new Parameters(['status' => $status, 'enabled' => $enabled, 'limit' => new QueryParameter(), 'X-Private' => $header]));

    self::assertSame(
      ['status' => 'draft', 'enabled' => false, 'limit' => '10', 'legacy' => 'retained'],
      $reader($operation, ['filters' => ['status' => 'draft', 'enabled' => 'true', 'limit' => '10', 'legacy' => 'retained']]),
    );
    self::assertSame(['enabled' => false], $reader($operation, ['filters' => 'invalid legacy value']));
  }

  /**
   * Method testOwnerQueriesCopyTheRequestAndPreserveNestedValues
   *
   * @access public
   *
   * @param Closure(Get, Request|null): InputBag<bool|float|int|string> $reader the owner-local HTTP bridge
   *
   * @return void
   */
  #[DataProvider('queryReaders')]
  public function testOwnerQueriesCopyTheRequestAndPreserveNestedValues(Closure $reader): void
  {
    $order = new QueryParameter();
    $order->setValue('desc');
    $status = new QueryParameter();
    $status->setValue(['draft', 'planned']);
    $operation = new Get(parameters: new Parameters(['order[createdAt]' => $order, 'status[]' => $status, 'limit' => new QueryParameter()]));
    $request = Request::create('/items?order[name]=asc&status=draft&limit=10');
    $original = $request->query->all();
    $query = $reader($operation, $request);

    self::assertSame(['order' => ['name' => 'asc', 'createdAt' => 'desc'], 'status' => ['draft', 'planned'], 'limit' => '10'], $query->all());
    self::assertSame($original, $request->query->all());
    $query->set('limit', '50');
    self::assertSame('10', $request->query->get('limit'));
    self::assertSame(['order' => ['createdAt' => 'desc'], 'status' => ['draft', 'planned']], $reader($operation, null)->all());
  }

  /**
   * Method testOwnerHeadersPreserveListsCasingNullAndRequestSyntax
   *
   * @access public
   *
   * @param Closure(Get, Request|null): HeaderBag $reader the owner-local HTTP bridge
   *
   * @return void
   */
  #[DataProvider('headerReaders')]
  public function testOwnerHeadersPreserveListsCasingNullAndRequestSyntax(Closure $reader): void
  {
    $etag = new HeaderParameter();
    $etag->setValue('W/"revision-2", "revision-3"');
    $ranges = new HeaderParameter();
    $ranges->setValue(['primary' => 'bytes=0-9', 5 => 'bytes=20-']);
    $cleared = new HeaderParameter();
    $cleared->setValue(null);
    $query = new QueryParameter();
    $query->setValue('query-only');
    $operation = new Get(parameters: new Parameters(['if-match' => $etag, 'Range' => $ranges, 'X-Cleared' => $cleared, 'Accept' => new HeaderParameter(), 'X-Query' => $query]));
    $request = Request::create('/items', server: ['HTTP_IF_MATCH' => '"revision-1"', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_X_CLEARED' => 'previous']);
    $original = $request->headers->all();
    $headers = $reader($operation, $request);

    self::assertSame('W/"revision-2", "revision-3"', $headers->get('IF-MATCH'));
    self::assertSame(['bytes=0-9', 'bytes=20-'], $headers->all('range'));
    self::assertSame(['previous'], $headers->all('x-cleared'));
    self::assertSame('application/ld+json', $headers->get('accept'));
    self::assertFalse($headers->has('x-query'));
    self::assertSame($original, $request->headers->all());
    self::assertSame(['bytes=0-9', 'bytes=20-'], $reader($operation, null)->all('range'));
  }

  /**
   * Method testOwnerHeadersKeepInvalidValuesAtHttp400
   *
   * @access public
   *
   * @param Closure(Get, Request|null): HeaderBag $reader the owner-local HTTP bridge
   *
   * @return void
   */
  #[DataProvider('headerReaders')]
  public function testOwnerHeadersKeepInvalidValuesAtHttp400(Closure $reader): void
  {
    $header = new HeaderParameter();
    $header->setValue(['valid', false]);
    $operation = new Get(parameters: new Parameters(['If-Match' => $header]));

    try {
      $reader($operation, null);
      self::fail('Invalid parsed headers must fail at the HTTP boundary.');
    } catch (BadRequestHttpException $exception) {
      self::assertSame(400, $exception->getStatusCode());
      self::assertSame('Invalid header value.', $exception->getMessage());
      self::assertNull($exception->getPrevious());
    }
  }
  // #endregion
}
