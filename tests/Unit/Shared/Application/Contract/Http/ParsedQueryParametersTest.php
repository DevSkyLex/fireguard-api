<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application\Contract\Http;

use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\ParsedQueryParameters;

/**
 * Class ParsedQueryParametersTest
 *
 * Covers legacy fallback, nested query names and explicit parsed scalar values.
 *
 * @category Unit Tests
 */
final class ParsedQueryParametersTest extends TestCase
{
  // #region Methods
  /**
   * Method testParsedValuesKeepLegacyKeysAndOverrideFalseAndNull
   *
   * @access public
   *
   * @return void
   */
  public function testParsedValuesKeepLegacyKeysAndOverrideFalseAndNull(): void
  {
    $legacy = ['page' => '2', 'enabled' => 'true', 'name' => 'old', 'legacy' => 'retained'];
    $parsed = ['page' => 2, 'enabled' => false, 'name' => null];

    self::assertSame(
      ['page' => 2, 'enabled' => false, 'name' => null, 'legacy' => 'retained'],
      ParsedQueryParameters::merge($parsed, $legacy),
    );
    self::assertSame(['page' => '2', 'enabled' => 'true', 'name' => 'old', 'legacy' => 'retained'], $legacy);
    self::assertSame(['page' => 2, 'enabled' => false, 'name' => null], $parsed);
  }

  /**
   * Method testBracketedNamesReplaceScalarParentsAndRetainNestedSiblings
   *
   * @access public
   *
   * @return void
   */
  public function testBracketedNamesReplaceScalarParentsAndRetainNestedSiblings(): void
  {
    self::assertSame(
      ['order' => ['name' => 'asc', 'createdAt' => 'desc'], 'scope' => ['owner' => ['id' => 'owner-id']], 'status' => ['draft', 'planned']],
      ParsedQueryParameters::merge(
        ['order[createdAt]' => 'desc', 'scope[owner][id]' => 'owner-id', 'status[]' => ['draft', 'planned']],
        ['order' => ['name' => 'asc'], 'scope' => 'legacy', 'status' => 'draft'],
      ),
    );
  }

  /**
   * Method testNamesWithoutSegmentsAreIgnoredAndNumericLeavesRemainArrayIndices
   *
   * @access public
   *
   * @return void
   */
  public function testNamesWithoutSegmentsAreIgnoredAndNumericLeavesRemainArrayIndices(): void
  {
    self::assertSame(
      ['legacy' => true, 'ids' => [1 => 'second']],
      ParsedQueryParameters::merge(['[]' => 'ignored', '' => 'ignored', 'ids[1]' => 'second'], ['legacy' => true]),
    );
  }
  // #endregion
}
