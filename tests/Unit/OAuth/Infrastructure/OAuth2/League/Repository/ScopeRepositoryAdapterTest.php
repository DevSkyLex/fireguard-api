<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\OAuth2\League\Repository;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use OAuth\Infrastructure\OAuth2\League\Entity\Scope as LeagueScope;
use OAuth\Infrastructure\OAuth2\League\Repository\ScopeRepositoryAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ScopeRepositoryAdapterTest.
 *
 * @category Repository Adapter Tests
 */
#[CoversClass(className: ScopeRepositoryAdapter::class)]
final class ScopeRepositoryAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testGetScopeEntityByIdentifierReturnsNullForInvalidValues(): void
  {
    $adapter = new ScopeRepositoryAdapter();

    self::assertNull($adapter->getScopeEntityByIdentifier(123));
    self::assertNull($adapter->getScopeEntityByIdentifier(''));
    self::assertNull($adapter->getScopeEntityByIdentifier('INVALID'));
  }

  #[Test]
  public function testGetScopeEntityByIdentifierReturnsScope(): void
  {
    $adapter = new ScopeRepositoryAdapter();

    $scope = $adapter->getScopeEntityByIdentifier('OPENID');

    self::assertInstanceOf(LeagueScope::class, $scope);
    self::assertSame('OPENID', $scope->getIdentifier());
  }

  #[Test]
  public function testFinalizeScopesReturnsInput(): void
  {
    $adapter = new ScopeRepositoryAdapter();
    $scope = $this->createStub(ScopeEntityInterface::class);

    $result = $adapter->finalizeScopes([$scope], 'authorization_code', $this->createStub(\League\OAuth2\Server\Entities\ClientEntityInterface::class));

    self::assertSame([$scope], $result);
  }
  // #endregion
}
