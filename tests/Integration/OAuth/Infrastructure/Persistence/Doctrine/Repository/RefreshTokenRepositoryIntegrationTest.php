<?php

declare(strict_types=1);

namespace Tests\Integration\OAuth\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Domain\Model\Token\RefreshToken;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Infrastructure\Persistence\Doctrine\Repository\RefreshTokenRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test RefreshTokenRepositoryIntegrationTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RefreshTokenRepository::class)]
final class RefreshTokenRepositoryIntegrationTest extends KernelTestCase
{
  // #region Constants
  private const string ENCRYPTION_KEY = 'def000000000000000000000000000000000000000000000000000000000abcd';
  // #endregion

  // #region Properties
  private EntityManagerInterface $entityManager;

  private RefreshTokenRepository $repository;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;

    $this->repository = new RefreshTokenRepository(
      entityManager: $this->entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );
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
    $token = new RefreshToken(
      identifier: 'rt-integration-save-find-0001',
      expiryDateTime: new DateTimeImmutable('+1 day'),
      accessTokenIdentifier: 'at-linked-0001',
      clientIdentifier: new OAuthClientIdentifier('123e4567-e89b-12d3-a456-426614174910'),
      isRevoked: false,
    );

    $this->repository->save($token);

    $found = $this->repository->find('rt-integration-save-find-0001');

    self::assertNotNull($found);
    self::assertSame('rt-integration-save-find-0001', $found->identifier());
    self::assertSame('at-linked-0001', $found->accessTokenIdentifier());
    self::assertSame('123e4567-e89b-12d3-a456-426614174910', $found->clientIdentifier()->value);
    self::assertFalse($found->isRevoked());
  }

  #[Test]
  public function testFindReturnsNullWhenNotFound(): void
  {
    self::assertNull($this->repository->find('rt-integration-missing-0002'));
  }

  #[Test]
  public function testSaveUpdatesExistingTokenRevocation(): void
  {
    $token = new RefreshToken(
      identifier: 'rt-integration-revoke-0003',
      expiryDateTime: new DateTimeImmutable('+1 day'),
      accessTokenIdentifier: 'at-linked-0003',
      clientIdentifier: new OAuthClientIdentifier('123e4567-e89b-12d3-a456-426614174911'),
      isRevoked: false,
    );

    $this->repository->save($token);

    $token->revoke();
    $this->repository->save($token);

    $found = $this->repository->find('rt-integration-revoke-0003');

    self::assertNotNull($found);
    self::assertTrue($found->isRevoked());
  }

  #[Test]
  public function testFindByEncryptedTokenReturnsNullForEmptyString(): void
  {
    self::assertNull($this->repository->findByEncryptedToken(''));
  }

  #[Test]
  public function testFindByEncryptedTokenReturnsNullForInvalidCiphertext(): void
  {
    self::assertNull($this->repository->findByEncryptedToken('not-a-valid-encrypted-token'));
  }
  // #endregion
}
