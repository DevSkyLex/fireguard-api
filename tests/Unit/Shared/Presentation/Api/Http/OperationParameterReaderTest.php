<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Http;

use ApiPlatform\Metadata\{Get, HeaderParameter, Parameters, QueryParameter};
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Http\OperationParameterReader;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test OperationParameterReaderTest.
 *
 * @category Unit Tests
 *
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
  // #endregion
}
