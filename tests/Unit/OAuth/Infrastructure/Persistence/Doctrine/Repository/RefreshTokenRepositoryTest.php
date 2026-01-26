<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Defuse\Crypto\Crypto;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Domain\Model\Token\RefreshToken;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Infrastructure\Persistence\Doctrine\Record\RefreshTokenRecord;
use OAuth\Infrastructure\Persistence\Doctrine\Repository\RefreshTokenRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function json_encode;

/**
 * Test RefreshTokenRepositoryTest.
 *
 * @category Repository Tests
 */
#[CoversClass(className: RefreshTokenRepository::class)]
final class RefreshTokenRepositoryTest extends TestCase
{
  private const string ENCRYPTION_KEY = 'test-encryption-key';

  // #region Methods
  #[Test]
  public function testSavePersistsRefreshToken(): void
  {
    $token = new RefreshToken(
      identifier: 'refresh-id',
      expiryDateTime: new DateTimeImmutable('2024-01-01 00:00:00'),
      accessTokenIdentifier: 'access-id',
      clientIdentifier: new OAuthClientIdentifier('client-123'),
    );

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(RefreshTokenRecord::class, 'refresh-id')
      ->willReturn(null);
    $entityManager->expects(self::once())
      ->method('persist')
      ->with(self::isInstanceOf(RefreshTokenRecord::class));
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new RefreshTokenRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $repository->save($token);
  }

  #[Test]
  public function testSaveUpdatesExistingRefreshToken(): void
  {
    $record = $this->createRecord();
    $record->isRevoked = true;

    $token = new RefreshToken(
      identifier: 'refresh-id',
      expiryDateTime: new DateTimeImmutable('2024-02-01 00:00:00'),
      accessTokenIdentifier: 'access-id',
      clientIdentifier: new OAuthClientIdentifier('client-123'),
      isRevoked: false,
    );

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(RefreshTokenRecord::class, 'refresh-id')
      ->willReturn($record);
    $entityManager->expects(self::once())
      ->method('persist')
      ->with($record);
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new RefreshTokenRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $repository->save($token);

    self::assertFalse($record->isRevoked);
  }

  #[Test]
  public function testFindReturnsNullWhenMissing(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(RefreshTokenRecord::class, 'missing')
      ->willReturn(null);

    $repository = new RefreshTokenRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    self::assertNull($repository->find('missing'));
  }

  #[Test]
  public function testFindByEncryptedTokenReturnsRefreshToken(): void
  {
    $record = $this->createRecord();

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(RefreshTokenRecord::class, 'refresh-id')
      ->willReturn($record);

    $repository = new RefreshTokenRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $encrypted = $this->encryptPayload(['refresh_token_id' => 'refresh-id']);

    $token = $repository->findByEncryptedToken($encrypted);

    self::assertInstanceOf(RefreshToken::class, $token);
    self::assertSame('refresh-id', $token->identifier());
  }

  #[Test]
  public function testFindByEncryptedTokenReturnsNullWhenEmpty(): void
  {
    $repository = new RefreshTokenRepository(
      entityManager: $this->createMock(EntityManagerInterface::class),
      encryptionKey: self::ENCRYPTION_KEY,
    );

    self::assertNull($repository->findByEncryptedToken(''));
  }

  #[Test]
  public function testFindByEncryptedTokenReturnsNullWhenPayloadInvalid(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::never())->method('find');

    $repository = new RefreshTokenRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $encrypted = Crypto::encryptWithPassword('not-json', self::ENCRYPTION_KEY);

    self::assertNull($repository->findByEncryptedToken($encrypted));
  }

  #[Test]
  public function testFindByEncryptedTokenReturnsNullWhenIdentifierMissing(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::never())->method('find');

    $repository = new RefreshTokenRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $encrypted = $this->encryptPayload(['refresh_token_id' => '']);

    self::assertNull($repository->findByEncryptedToken($encrypted));
  }

  #[Test]
  public function testFindByEncryptedTokenReturnsNullWhenDecryptFails(): void
  {
    $repository = new RefreshTokenRepository(
      entityManager: $this->createMock(EntityManagerInterface::class),
      encryptionKey: self::ENCRYPTION_KEY,
    );

    self::assertNull($repository->findByEncryptedToken('invalid'));
  }

  private function createRecord(): RefreshTokenRecord
  {
    $record = new RefreshTokenRecord();
    $record->identifier = 'refresh-id';
    $record->accessTokenIdentifier = 'access-id';
    $record->clientIdentifier = 'client-123';
    $record->expiry = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->isRevoked = false;

    return $record;
  }

  /**
   * @param array<string, mixed> $payload
   */
  private function encryptPayload(array $payload): string
  {
    $json = json_encode($payload);
    self::assertIsString($json);

    return Crypto::encryptWithPassword($json, self::ENCRYPTION_KEY);
  }
  // #endregion
}
