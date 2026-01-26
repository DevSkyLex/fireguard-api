<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Domain\Model\Token\AccessToken;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Infrastructure\Persistence\Doctrine\Record\AccessTokenRecord;
use OAuth\Infrastructure\Persistence\Doctrine\Repository\AccessTokenRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AccessTokenRepositoryTest.
 *
 * @category Repository Tests
 */
#[CoversClass(className: AccessTokenRepository::class)]
final class AccessTokenRepositoryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSavePersistsAccessToken(): void
  {
    $token = new AccessToken(
      identifier: 'access-id',
      clientIdentifier: new OAuthClientIdentifier('client-123'),
      expiry: new DateTimeImmutable('2024-01-01 00:00:00'),
      scopes: Scopes::fromArray(['OPENID']),
      userIdentifier: 'user-1',
    );

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(AccessTokenRecord::class, 'access-id')
      ->willReturn(null);
    $entityManager->expects(self::once())
      ->method('persist')
      ->with(self::callback(function (AccessTokenRecord $record) use ($token): bool {
        return $record->identifier === $token->identifier()
          && $record->clientIdentifier === (string) $token->clientIdentifier()
          && $record->userIdentifier === $token->userIdentifier();
      }));
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new AccessTokenRepository(entityManager: $entityManager);

    $repository->save($token);
  }

  #[Test]
  public function testFindReturnsAccessToken(): void
  {
    $record = new AccessTokenRecord();
    $record->identifier = 'access-id';
    $record->clientIdentifier = 'client-123';
    $record->userIdentifier = 'user-1';
    $record->scopes = ['OPENID'];
    $record->expiry = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->isRevoked = true;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(AccessTokenRecord::class, 'access-id')
      ->willReturn($record);

    $repository = new AccessTokenRepository(entityManager: $entityManager);

    $token = $repository->find('access-id');

    self::assertInstanceOf(AccessToken::class, $token);
    self::assertSame('access-id', $token->identifier());
    self::assertTrue($token->isRevoked());
  }
  // #endregion
}
