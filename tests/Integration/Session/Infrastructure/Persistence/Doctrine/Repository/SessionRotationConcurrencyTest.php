<?php

declare(strict_types=1);

namespace Tests\Integration\Session\Infrastructure\Persistence\Doctrine\Repository;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\SessionId;
use Session\Infrastructure\Persistence\Doctrine\Repository\SessionRepository;
use Shared\Domain\ValueObject\{IpAddress, UserAgent};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function json_decode;

use const JSON_THROW_ON_ERROR;

#[SkipDatabaseRollback]
final class SessionRotationConcurrencyTest extends KernelTestCase
{
  private const string ID = 'bd000000-0000-4000-8000-000000000091';

  private Connection $a;

  private Connection $b;

  private SessionRepository $first;

  private SessionRepository $second;

  protected function setUp(): void
  {
    self::bootKernel();
    $em = self::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $this->a = $em->getConnection();
    $url = $_ENV['AUTH_DATABASE_URL'] ?? $_SERVER['AUTH_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->b = DriverManager::getConnection(['url' => $url]);
    $this->a->executeStatement('DELETE FROM sessions WHERE id = ?', [self::ID]);
    $this->first = new SessionRepository($em);
    $this->second = new SessionRepository(new EntityManager($this->b, $em->getConfiguration()));
    $this->first->save(Session::create(
      new SessionId(self::ID),
      'rotation-owner',
      new IpAddress('127.0.0.1'),
      new UserAgent('Concurrency test'),
      accessTokenId: 'access-before',
      refreshTokenId: 'refresh-before',
    ));
  }

  protected function tearDown(): void
  {
    foreach ([$this->a, $this->b] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->a->executeStatement('DELETE FROM sessions WHERE id = ?', [self::ID]);
    $this->b->close();
    parent::tearDown();
  }

  public function testOnlyOneConcurrentRotationCanConsumeThePair(): void
  {
    $this->a->beginTransaction();
    self::assertTrue($this->first->rotateTokens('refresh-before', 'access-before', 'access-winner', 'refresh-winner'));
    $this->b->executeStatement("SET lock_timeout = '150ms'");

    try {
      $this->second->rotateTokens('refresh-before', 'access-before', 'access-loser', 'refresh-loser');
      self::fail('The second refresh must wait for the first transaction.');
    } catch (DriverException $error) {
      self::assertSame('55P03', $error->getSQLState());
    }
    $this->a->commit();
    self::assertFalse($this->second->rotateTokens('refresh-before', 'access-before', 'access-loser', 'refresh-loser'));
    self::assertNotNull($this->second->findByAccessTokenId('access-winner'));
    self::assertNull($this->second->findByAccessTokenId('access-loser'));
  }

  public function testStaleIndividualRevocationKeepsRotatedPairAndErasesGeography(): void
  {
    $this->a->executeStatement("UPDATE sessions SET metadata = '{\"country\":\"FR\",\"city\":\"Paris\",\"browser\":\"Test\"}' WHERE id = ?", [self::ID]);
    $stale = $this->second->findById(new SessionId(self::ID));
    self::assertNotNull($stale);
    self::assertTrue($this->first->rotateTokens('refresh-before', 'access-before', 'access-winner', 'refresh-winner'));
    $stale->revoke();
    $this->second->save($stale);
    $row = $this->a->fetchAssociative('SELECT * FROM sessions WHERE id = ?', [self::ID]);
    self::assertIsArray($row);
    self::assertSame('access-winner', $row['access_token_id']);
    self::assertNotNull($row['revoked_at']);
    self::assertIsString($row['metadata']);
    $metadata = json_decode($row['metadata'], true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($metadata);
    self::assertArrayNotHasKey('country', $metadata);
    self::assertArrayNotHasKey('city', $metadata);
    self::assertSame('Test', $metadata['browser']);
    self::assertFalse($this->first->rotateTokens('refresh-winner', 'access-winner', 'access-after-revoke', 'refresh-after-revoke'));
  }

  public function testRevocationWinsAgainstAConcurrentRefresh(): void
  {
    $this->a->beginTransaction();
    self::assertSame(1, $this->first->revokeAllForUser('rotation-owner'));
    $this->b->executeStatement("SET lock_timeout = '150ms'");

    try {
      $this->second->rotateTokens('refresh-before', 'access-before', 'access-after', 'refresh-after');
      self::fail('Refresh must wait for the revocation transaction.');
    } catch (DriverException $error) {
      self::assertSame('55P03', $error->getSQLState());
    }
    $this->a->commit();
    self::assertFalse($this->second->rotateTokens('refresh-before', 'access-before', 'access-after', 'refresh-after'));
    self::assertTrue($this->second->findByAccessTokenId('access-before')?->isRevoked());
  }
}
