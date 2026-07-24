<?php

declare(strict_types=1);

namespace Tests\Integration\OAuth\Infrastructure\Adapter\Auth;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Domain\Model\Token\AccessToken;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Infrastructure\Adapter\Auth\AccessTokenLookupAdapter;
use OAuth\Infrastructure\Persistence\Doctrine\Repository\AccessTokenRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test AccessTokenLookupAdapterIntegrationTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: AccessTokenLookupAdapter::class)]
final class AccessTokenLookupAdapterIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;

  private AccessTokenRepository $accessTokenRepository;

  private AccessTokenLookupAdapter $adapter;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;

    $this->accessTokenRepository = new AccessTokenRepository(entityManager: $this->entityManager);
    $this->adapter = new AccessTokenLookupAdapter(accessTokenRepository: $this->accessTokenRepository);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testFindReturnsStatusForActiveToken(): void
  {
    $token = new AccessToken(
      identifier: 'atl-integration-active-0001',
      clientIdentifier: new OAuthClientIdentifier('123e4567-e89b-12d3-a456-426614174930'),
      expiry: new DateTimeImmutable('+1 hour'),
      scopes: Scopes::fromArray(['OPENID', 'EMAIL']),
      userIdentifier: 'user-atl-0001',
      isRevoked: false,
    );
    $this->accessTokenRepository->save($token);

    $status = $this->adapter->find('atl-integration-active-0001');

    self::assertNotNull($status);
    self::assertSame(['OPENID', 'EMAIL'], $status->scopes);
    self::assertFalse($status->revoked);
    self::assertFalse($status->expired);
  }

  #[Test]
  public function testFindReflectsRevokedAndExpiredState(): void
  {
    $token = new AccessToken(
      identifier: 'atl-integration-revoked-0002',
      clientIdentifier: new OAuthClientIdentifier('123e4567-e89b-12d3-a456-426614174931'),
      expiry: new DateTimeImmutable('-1 hour'),
      scopes: Scopes::fromArray(['OPENID']),
      userIdentifier: 'user-atl-0002',
      isRevoked: true,
    );
    $this->accessTokenRepository->save($token);

    $status = $this->adapter->find('atl-integration-revoked-0002');

    self::assertNotNull($status);
    self::assertTrue($status->revoked);
    self::assertTrue($status->expired);
  }

  #[Test]
  public function testFindReturnsNullWhenTokenIsMissing(): void
  {
    self::assertNull($this->adapter->find('atl-integration-missing-0003'));
  }
  // #endregion
}
