<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\OAuth2\League\Repository;

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
    $adapter = new ScopeRepositoryAdapter($this->createStub(\OAuth\Application\Port\Outbound\Client\OAuthClientRepositoryPort::class));

    self::assertNull($adapter->getScopeEntityByIdentifier(123));
    self::assertNull($adapter->getScopeEntityByIdentifier(''));
    self::assertNull($adapter->getScopeEntityByIdentifier('INVALID'));
  }

  #[Test]
  public function testGetScopeEntityByIdentifierReturnsScope(): void
  {
    $adapter = new ScopeRepositoryAdapter($this->createStub(\OAuth\Application\Port\Outbound\Client\OAuthClientRepositoryPort::class));

    $scope = $adapter->getScopeEntityByIdentifier('OPENID');

    self::assertInstanceOf(LeagueScope::class, $scope);
    self::assertSame('OPENID', $scope->getIdentifier());
  }

  #[Test]
  public function testFinalizeScopesPreservesRegisteredScopes(): void
  {
    $adapter = $this->registeredAdapter();
    $scope = $adapter->getScopeEntityByIdentifier('OPENID');
    self::assertNotNull($scope);
    $client = new \OAuth\Infrastructure\OAuth2\League\Entity\Client('client-123', 'Client', 'https://example.test/callback');

    self::assertSame([$scope], $adapter->finalizeScopes([$scope], 'authorization_code', $client));
  }

  #[Test]
  public function testEmptyDelegationFailsWithoutImplicitBusinessScopes(): void
  {
    $client = new \OAuth\Infrastructure\OAuth2\League\Entity\Client('client-123', 'Client', 'https://example.test/callback');

    try {
      $this->registeredAdapter()->finalizeScopes([], 'authorization_code', $client);
      self::fail('The existing nonempty scope invariant must remain explicit.');
    } catch (\League\OAuth2\Server\Exception\OAuthServerException $exception) {
      self::assertSame('invalid_scope', $exception->getErrorType());
    }
  }

  #[Test]
  public function testOidcOnlyClientCannotRequestBusinessScopes(): void
  {
    $adapter = $this->registeredAdapter();
    $scope = $adapter->getScopeEntityByIdentifier('ADMIN');
    self::assertNotNull($scope);
    $client = new \OAuth\Infrastructure\OAuth2\League\Entity\Client('client-123', 'Client', 'https://example.test/callback');

    $this->expectException(\League\OAuth2\Server\Exception\OAuthServerException::class);
    $adapter->finalizeScopes([$scope], 'authorization_code', $client);
  }

  #[Test]
  public function testRegisteredClientCannotUseAnotherGrantEvenWithNoScopes(): void
  {
    $client = new \OAuth\Infrastructure\OAuth2\League\Entity\Client('client-123', 'Client', 'https://example.test/callback');
    $this->expectException(\League\OAuth2\Server\Exception\OAuthServerException::class);
    $this->registeredAdapter()->finalizeScopes([], 'client_credentials', $client);
  }

  #[Test]
  public function testMissingClientFailsClosedEvenWithNoScopes(): void
  {
    $adapter = new ScopeRepositoryAdapter($this->createStub(\OAuth\Application\Port\Outbound\Client\OAuthClientRepositoryPort::class));
    $client = new \OAuth\Infrastructure\OAuth2\League\Entity\Client('missing-client', 'Missing', 'https://example.test/callback');
    $this->expectException(\League\OAuth2\Server\Exception\OAuthServerException::class);
    $adapter->finalizeScopes([], 'authorization_code', $client);
  }

  #[Test]
  public function testInactiveClientFailsClosedEvenWithNoScopes(): void
  {
    $client = new \OAuth\Infrastructure\OAuth2\League\Entity\Client('client-123', 'Client', 'https://example.test/callback');
    $this->expectException(\League\OAuth2\Server\Exception\OAuthServerException::class);
    $this->registeredAdapter(false)->finalizeScopes([], 'authorization_code', $client);
  }

  private function registeredAdapter(bool $active = true): ScopeRepositoryAdapter
  {
    $repository = $this->createStub(\OAuth\Application\Port\Outbound\Client\OAuthClientRepositoryPort::class);
    $repository->method('find')->willReturn(new \OAuth\Domain\Model\Client\OAuthClient(
      identifier: new \OAuth\Domain\ValueObject\Client\OAuthClientIdentifier('client-123'),
      name: 'OIDC client',
      grantTypes: [\OAuth\Domain\ValueObject\Security\GrantType::AUTHORIZATION_CODE],
      scopes: [\OAuth\Domain\ValueObject\Scope\Scope::OPENID],
      isActive: $active,
    ));

    return new ScopeRepositoryAdapter($repository);
  }
  // #endregion
}
