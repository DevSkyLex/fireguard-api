<?php

declare(strict_types=1);

namespace Tests\Integration\OAuth\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Domain\Model\Token\AccessToken;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Infrastructure\Persistence\Doctrine\Repository\AccessTokenRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test AccessTokenRepositoryIntegrationTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: AccessTokenRepository::class)]
final class AccessTokenRepositoryIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;

  private AccessTokenRepository $repository;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;

    $this->repository = new AccessTokenRepository(entityManager: $this->entityManager);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testSaveAndFindRoundTrip(): void
  {
    $token = new AccessToken(
      identifier: 'at-integration-save-find-0001',
      clientIdentifier: new OAuthClientIdentifier('123e4567-e89b-12d3-a456-426614174900'),
      expiry: new DateTimeImmutable('+1 hour'),
      scopes: Scopes::fromArray(['OPENID', 'EMAIL']),
      userIdentifier: 'user-at-0001',
      isRevoked: false,
    );

    $this->repository->save($token);

    $found = $this->repository->find('at-integration-save-find-0001');

    self::assertNotNull($found);
    self::assertSame('at-integration-save-find-0001', $found->identifier());
    self::assertSame('123e4567-e89b-12d3-a456-426614174900', $found->clientIdentifier()->value);
    self::assertSame('user-at-0001', $found->userIdentifier());
    self::assertSame(['OPENID', 'EMAIL'], $found->scopes()->toArray());
    self::assertFalse($found->isRevoked());
  }

  #[Test]
  public function testFindReturnsNullWhenNotFound(): void
  {
    self::assertNull($this->repository->find('at-integration-missing-0002'));
  }

  #[Test]
  public function testSaveUpdatesExistingTokenRevocation(): void
  {
    $token = new AccessToken(
      identifier: 'at-integration-revoke-0003',
      clientIdentifier: new OAuthClientIdentifier('123e4567-e89b-12d3-a456-426614174901'),
      expiry: new DateTimeImmutable('+1 hour'),
      scopes: Scopes::fromArray(['OPENID']),
      userIdentifier: 'user-at-0003',
      isRevoked: false,
    );

    $this->repository->save($token);

    $token->revoke();
    $this->repository->save($token);

    $found = $this->repository->find('at-integration-revoke-0003');

    self::assertNotNull($found);
    self::assertTrue($found->isRevoked());
  }

  #[Test]
  public function testSavePersistsExpiredTokenState(): void
  {
    $token = new AccessToken(
      identifier: 'at-integration-expired-0004',
      clientIdentifier: new OAuthClientIdentifier('123e4567-e89b-12d3-a456-426614174902'),
      expiry: new DateTimeImmutable('-1 hour'),
      scopes: Scopes::fromArray(['OPENID']),
      userIdentifier: null,
      isRevoked: false,
    );

    $this->repository->save($token);

    $found = $this->repository->find('at-integration-expired-0004');

    self::assertNotNull($found);
    self::assertNull($found->userIdentifier());
    self::assertTrue($found->isExpired());
  }
  // #endregion
}
