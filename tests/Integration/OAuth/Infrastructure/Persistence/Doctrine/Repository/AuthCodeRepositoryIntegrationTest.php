<?php

declare(strict_types=1);

namespace Tests\Integration\OAuth\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Domain\Model\Token\AuthCode;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Infrastructure\Persistence\Doctrine\Repository\AuthCodeRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test AuthCodeRepositoryIntegrationTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: AuthCodeRepository::class)]
final class AuthCodeRepositoryIntegrationTest extends KernelTestCase
{
  // #region Constants
  private const string ENCRYPTION_KEY = 'def000000000000000000000000000000000000000000000000000000000abcd';
  // #endregion

  // #region Properties
  private EntityManagerInterface $entityManager;

  private AuthCodeRepository $repository;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;

    $this->repository = new AuthCodeRepository(
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
    $authCode = new AuthCode(
      identifier: 'ac-integration-save-find-0001',
      expiryDateTime: new DateTimeImmutable('+10 minutes'),
      clientIdentifier: new OAuthClientIdentifier('123e4567-e89b-12d3-a456-426614174920'),
      userIdentifier: 'user-ac-0001',
      scopes: Scopes::fromArray(['OPENID', 'PROFILE']),
      redirectUri: 'https://example.com/callback',
      nonce: 'nonce-0001',
      isRevoked: false,
    );

    $this->repository->save($authCode);

    $found = $this->repository->find('ac-integration-save-find-0001');

    self::assertNotNull($found);
    self::assertSame('ac-integration-save-find-0001', $found->identifier());
    self::assertSame('123e4567-e89b-12d3-a456-426614174920', $found->clientIdentifier()->value);
    self::assertSame('user-ac-0001', $found->userIdentifier());
    self::assertSame(['OPENID', 'PROFILE'], $found->scopes()->toArray());
    self::assertSame('https://example.com/callback', $found->redirectUri());
    self::assertSame('nonce-0001', $found->nonce());
    self::assertFalse($found->isRevoked());
  }

  #[Test]
  public function testFindReturnsNullWhenNotFound(): void
  {
    self::assertNull($this->repository->find('ac-integration-missing-0002'));
  }

  #[Test]
  public function testUpdateNonceChangesStoredNonce(): void
  {
    $authCode = new AuthCode(
      identifier: 'ac-integration-nonce-0003',
      expiryDateTime: new DateTimeImmutable('+10 minutes'),
      clientIdentifier: new OAuthClientIdentifier('123e4567-e89b-12d3-a456-426614174921'),
      userIdentifier: 'user-ac-0003',
      scopes: Scopes::fromArray(['OPENID']),
      redirectUri: 'https://example.com/callback',
      nonce: null,
      isRevoked: false,
    );

    $this->repository->save($authCode);

    $this->repository->updateNonce('ac-integration-nonce-0003', 'updated-nonce-0003');

    $found = $this->repository->find('ac-integration-nonce-0003');

    self::assertNotNull($found);
    self::assertSame('updated-nonce-0003', $found->nonce());
  }

  #[Test]
  public function testUpdateNonceIsNoOpForUnknownIdentifier(): void
  {
    $this->repository->updateNonce('ac-integration-unknown-0004', 'whatever');

    self::assertNull($this->repository->find('ac-integration-unknown-0004'));
  }

  #[Test]
  public function testFindByEncryptedCodeReturnsNullForEmptyString(): void
  {
    self::assertNull($this->repository->findByEncryptedCode(''));
  }

  #[Test]
  public function testFindByEncryptedCodeReturnsNullForInvalidCiphertext(): void
  {
    self::assertNull($this->repository->findByEncryptedCode('not-a-valid-encrypted-code'));
  }
  // #endregion
}
