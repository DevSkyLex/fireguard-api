<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Defuse\Crypto\Crypto;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Domain\Model\Token\AuthCode;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Infrastructure\Persistence\Doctrine\Record\AuthCodeRecord;
use OAuth\Infrastructure\Persistence\Doctrine\Repository\AuthCodeRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function is_array;
use function json_encode;

/**
 * Test AuthCodeRepositoryTest.
 *
 * @category Repository Tests
 */
#[CoversClass(className: AuthCodeRepository::class)]
final class AuthCodeRepositoryTest extends TestCase
{
  private const string ENCRYPTION_KEY = 'test-encryption-key';

  // #region Methods
  #[Test]
  public function testSavePersistsAuthCode(): void
  {
    $authCode = new AuthCode(
      identifier: 'code-1',
      expiryDateTime: new DateTimeImmutable('2024-01-01 00:00:00'),
      clientIdentifier: new OAuthClientIdentifier('client-123'),
      userIdentifier: 'user-1',
      scopes: Scopes::fromArray(['OPENID']),
      redirectUri: 'https://example.com/callback',
      nonce: 'nonce',
    );

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(AuthCodeRecord::class, 'code-1')
      ->willReturn(null);
    $entityManager->expects(self::once())
      ->method('persist')
      ->with(self::isInstanceOf(AuthCodeRecord::class));
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new AuthCodeRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $repository->save($authCode);
  }

  #[Test]
  public function testFindByEncryptedCodeReturnsAuthCode(): void
  {
    $record = $this->createRecord();

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(AuthCodeRecord::class, 'code-1')
      ->willReturn($record);

    $repository = new AuthCodeRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $encrypted = $this->encryptPayload(['auth_code_id' => 'code-1']);

    $authCode = $repository->findByEncryptedCode($encrypted);

    self::assertInstanceOf(AuthCode::class, $authCode);
    self::assertSame('code-1', $authCode->identifier());
  }

  #[Test]
  public function testUpdateNonceUsesEncryptedIdentifier(): void
  {
    $record = $this->createRecord();

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(AuthCodeRecord::class, 'code-1')
      ->willReturn($record);
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new AuthCodeRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $encrypted = $this->encryptPayload(['auth_code_id' => 'code-1']);

    $repository->updateNonce($encrypted, 'new-nonce');

    self::assertSame('new-nonce', $record->nonce);
  }

  #[Test]
  public function testFindReturnsNullWhenMissing(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(AuthCodeRecord::class, 'missing')
      ->willReturn(null);

    $repository = new AuthCodeRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    self::assertNull($repository->find('missing'));
  }

  #[Test]
  public function testFindByEncryptedCodeReturnsNullWhenEmpty(): void
  {
    $repository = new AuthCodeRepository(
      entityManager: $this->createStub(EntityManagerInterface::class),
      encryptionKey: self::ENCRYPTION_KEY,
    );

    self::assertNull($repository->findByEncryptedCode(''));
  }

  #[Test]
  public function testFindByEncryptedCodeReturnsNullForInvalidPayload(): void
  {
    $repository = new AuthCodeRepository(
      entityManager: $this->createStub(EntityManagerInterface::class),
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $encrypted = $this->encryptPayload('not-json');

    self::assertNull($repository->findByEncryptedCode($encrypted));
  }

  #[Test]
  public function testFindByEncryptedCodeReturnsNullWhenIdentifierMissing(): void
  {
    $repository = new AuthCodeRepository(
      entityManager: $this->createStub(EntityManagerInterface::class),
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $encrypted = $this->encryptPayload(['auth_code_id' => '']);

    self::assertNull($repository->findByEncryptedCode($encrypted));
  }

  #[Test]
  public function testFindByEncryptedCodeReturnsNullWhenDecryptFails(): void
  {
    $repository = new AuthCodeRepository(
      entityManager: $this->createStub(EntityManagerInterface::class),
      encryptionKey: self::ENCRYPTION_KEY,
    );

    self::assertNull($repository->findByEncryptedCode('invalid'));
  }

  #[Test]
  public function testUpdateNonceReturnsWhenRecordMissing(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(AuthCodeRecord::class, 'missing')
      ->willReturn(null);
    $entityManager->expects(self::never())
      ->method('flush');

    $repository = new AuthCodeRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $repository->updateNonce('missing', 'nonce');
  }

  #[Test]
  public function testUpdateNonceReturnsWhenEncryptedIdentifierInvalid(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(AuthCodeRecord::class, 'invalid')
      ->willReturn(null);

    $repository = new AuthCodeRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $repository->updateNonce('invalid', 'nonce');
  }

  #[Test]
  public function testUpdateNonceReturnsWhenIdentifierEmpty(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(AuthCodeRecord::class, '')
      ->willReturn(null);
    $entityManager->expects(self::never())
      ->method('flush');

    $repository = new AuthCodeRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $repository->updateNonce('', 'nonce');
  }

  #[Test]
  public function testUpdateNonceReturnsWhenEncryptedPayloadMissingIdentifier(): void
  {
    $encrypted = $this->encryptPayload(['auth_code_id' => '']);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(AuthCodeRecord::class, $encrypted)
      ->willReturn(null);

    $repository = new AuthCodeRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $repository->updateNonce($encrypted, 'nonce');
  }

  #[Test]
  public function testUpdateNonceReturnsWhenEncryptedPayloadNotArray(): void
  {
    $encrypted = $this->encryptPayload('not-json');

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(AuthCodeRecord::class, $encrypted)
      ->willReturn(null);

    $repository = new AuthCodeRepository(
      entityManager: $entityManager,
      encryptionKey: self::ENCRYPTION_KEY,
    );

    $repository->updateNonce($encrypted, 'nonce');
  }

  private function createRecord(): AuthCodeRecord
  {
    $record = new AuthCodeRecord();
    $record->identifier = 'code-1';
    $record->clientIdentifier = 'client-123';
    $record->userIdentifier = 'user-1';
    $record->scopes = ['OPENID'];
    $record->redirectUri = 'https://example.com/callback';
    $record->nonce = 'nonce';
    $record->expiry = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->isRevoked = false;

    return $record;
  }

  /**
   * @param array<string, mixed>|string $payload
   */
  private function encryptPayload(array|string $payload): string
  {
    if (is_array($payload)) {
      $json = json_encode($payload);
      self::assertIsString($json);

      $data = $json;
    } else {
      $data = $payload;
    }

    return Crypto::encryptWithPassword($data, self::ENCRYPTION_KEY);
  }
  // #endregion
}
