<?php

declare(strict_types=1);

namespace Tests\Client\Infrastructure\Persistence\Doctrine\Repository;

use Client\Domain\Model\Client;
use Client\Infrastructure\Persistence\Doctrine\Mapper\ClientMapper;
use Client\Infrastructure\Persistence\Doctrine\Record\ClientRecord;
use Client\Infrastructure\Persistence\Doctrine\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Client\Domain\ValueObject\{
  ClientId,
  ClientName,
  ClientSecret,
};
use Shared\Domain\ValueObject\{
  GrantType,
  GrantTypes,
  RedirectUri,
  Scope,
  Scopes,
};

/**
 * Test ClientRepositoryTest
 * @final
 *
 * Test class for ClientRepository.
 *
 * @category Repository Tests
 * @package Tests\Client\Infrastructure\Persistence\Repository
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ClientRepository::class)]
final class ClientRepositoryTest extends TestCase
{
  //#region Methods
  /**
   * Method testSavePersistsClient
   *
   * Test that save persists a client
   * to the database
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testSavePersistsClient(): void
  {
    $client = $this->createTestClient();

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('find')
      ->willReturn(null); // New client, not found

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(self::equalTo(ClientRecord::class))
      ->willReturn($doctrineRepository);
    $entityManager->expects(self::once())
      ->method('persist')
      ->with(self::isInstanceOf(ClientRecord::class));
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new ClientRepository(entityManager: $entityManager);
    $repository->save(client: $client);
  }

  /**
   * Method testFindByIdReturnsClient
   *
   * Test that findById returns a client
   * when it exists
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testFindByIdReturnsClient(): void
  {
    $clientId = new ClientId(value: '123e4567-e89b-12d3-a456-426614174000');
    $record = $this->createTestRecord();

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('find')
      ->with(self::equalTo($clientId->value))
      ->willReturn($record);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(self::equalTo(ClientRecord::class))
      ->willReturn($doctrineRepository);

    $repository = new ClientRepository(entityManager: $entityManager);
    $client = $repository->findById(id: $clientId);

    self::assertInstanceOf(expected: Client::class, actual: $client);
    self::assertSame(expected: $clientId->value, actual: $client->id()->value);
  }

  /**
   * Method testFindByIdReturnsNullWhenNotFound
   *
   * Test that findById returns null
   * when client is not found
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testFindByIdReturnsNullWhenNotFound(): void
  {
    $clientId = new ClientId(value: '123e4567-e89b-12d3-a456-426614174000');

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('find')
      ->with(self::equalTo($clientId->value))
      ->willReturn(null);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->willReturn($doctrineRepository);

    $repository = new ClientRepository(entityManager: $entityManager);

    $result = $repository->findById(id: $clientId);
    self::assertNull(actual: $result);
  }

  /**
   * Method testDeleteRemovesClient
   *
   * Test that delete removes a client
   * from the database
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testDeleteRemovesClient(): void
  {
    $client = $this->createTestClient();
    $record = $this->createTestRecord();

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('find')
      ->willReturn($record);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(self::equalTo(ClientRecord::class))
      ->willReturn($doctrineRepository);
    $entityManager->expects(self::once())
      ->method('remove')
      ->with(self::isInstanceOf(ClientRecord::class));
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new ClientRepository(entityManager: $entityManager);
    $repository->delete(client: $client);
  }

  /**
   * Method createTestClient
   *
   * Helper method to create a test client
   *
   * @access private
   *
   * @return Client Test client instance
   */
  private function createTestClient(): Client
  {
    $hashedSecret = password_hash('test-secret', PASSWORD_BCRYPT);

    return Client::register(
      id: new ClientId(value: '123e4567-e89b-12d3-a456-426614174000'),
      name: new ClientName(value: 'Test Client'),
      secret: new ClientSecret(value: $hashedSecret),
      redirectUris: [new RedirectUri(value: 'https://example.com/callback')],
      grantTypes: new GrantTypes(GrantType::AUTHORIZATION_CODE),
      scopes: new Scopes(Scope::READ)
    );
  }

  private function createTestRecord(): ClientRecord
  {
    return ClientMapper::toRecord(client: $this->createTestClient());
  }
  //#endregion
}

