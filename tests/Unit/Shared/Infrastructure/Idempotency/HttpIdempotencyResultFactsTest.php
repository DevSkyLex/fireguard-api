<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\ResultMessage;
use Shared\Infrastructure\Idempotency\HttpIdempotencyResultFacts;

/**
 * Class HttpIdempotencyResultFactsTest
 *
 * Keeps public identifier paths ASCII and prevents result contributors from changing signed authority.
 *
 * @category Adapter Tests
 */
#[CoversClass(HttpIdempotencyResultFacts::class)]
final class HttpIdempotencyResultFactsTest extends TestCase
{
  // #region Methods
  /**
   * Method onlyDeclaredPublicAsciiIdentifierPathsCanSelectAResource
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function onlyDeclaredPublicAsciiIdentifierPathsCanSelectAResource(): void
  {
    $result = new readonly class () implements ResultMessage {
      /**
       * Property _id_09
       */
      public string $_id_09;

      /**
       * Method __construct
       *
       * @access public
       *
       * @return void
       */
      public function __construct()
      {
        $this->_id_09 = 'resource';
      }
    };

    self::assertSame('resource', HttpIdempotencyResultFacts::identifier($result, '_id_09', false));
  }

  /**
   * Method unsupportedIdentifierPathsCannotRevealResultFields
   *
   * @access public
   *
   * @param string $path unsupported owner declaration
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidPaths')]
  public function unsupportedIdentifierPathsCannotRevealResultFields(string $path): void
  {
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('result_unavailable');

    HttpIdempotencyResultFacts::identifier(new readonly class () implements ResultMessage {}, $path, false);
  }

  /**
   * Method invalidPaths
   *
   * @access public
   *
   * @return iterable<string, array{string}> malformed or non-ASCII identifier paths
   */
  public static function invalidPaths(): iterable
  {
    yield 'empty' => [''];
    yield 'leading digit' => ['9id'];
    yield 'non-ASCII trailing character' => ['idé'];
    yield 'empty segment' => ['view..id'];
    yield 'too many segments' => ['a.b.c.d.e'];
    yield 'method call' => ['id()'];
    yield 'trailing newline' => ["id\n"];
  }

  /**
   * Method resultProofCanAddFactsWhilePreservingTheOriginalScope
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function resultProofCanAddFactsWhilePreservingTheOriginalScope(): void
  {
    self::assertSame(['principalId' => 'actor', 'tenantId' => 'tenant', 'resourceId' => 'resource'], HttpIdempotencyResultFacts::merge(['principalId' => 'actor', 'tenantId' => 'tenant'], ['principalId' => 'actor', 'resourceId' => 'resource']));
  }

  /**
   * Method aResultContributorCannotChangeSignedAuthority
   *
   * @access public
   *
   * @param mixed $facts untrusted runtime result contribution
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidFacts')]
  public function aResultContributorCannotChangeSignedAuthority(mixed $facts): void
  {
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('authorization_unavailable');

    HttpIdempotencyResultFacts::merge(['principalId' => 'actor', 'tenantId' => 'tenant'], $facts);
  }

  /**
   * Method invalidFacts
   *
   * @access public
   *
   * @return iterable<string, array{mixed}> malformed or conflicting authority contributions
   */
  public static function invalidFacts(): iterable
  {
    yield 'non-array' => ['resource'];
    yield 'integer key' => [[0 => 'resource']];
    yield 'non-scalar value' => [['resourceId' => ['nested']]];
    yield 'principal changed' => [['principalId' => 'other']];
    yield 'tenant changed' => [['tenantId' => 'other']];
  }
  // #endregion
}
