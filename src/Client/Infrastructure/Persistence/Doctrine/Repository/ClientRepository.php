<?php

declare(strict_types=1);

namespace Client\Infrastructure\Persistence\Doctrine\Repository;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Domain\Model\Client;
use Client\Domain\ValueObject\ClientId;
use Client\Domain\ValueObject\ClientName;
use Client\Infrastructure\Persistence\Doctrine\Mapper\ClientMapper;
use Client\Infrastructure\Persistence\Doctrine\Record\ClientRecord;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * Repository ClientRepository
 * @final
 *
 * Doctrine implementation of the ClientRepositoryPort 
 * using Data Mapper pattern.
 *
 * @category Repository
 * @package Client\Infrastructure\Persistence\Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientRepository implements ClientRepositoryPort
{
  //#region Properties
  /**
   * Property repository
   *
   * The Doctrine entity repository 
   * for ClientRecord.
   * 
   * @access private
   * @since 1.0.0
   *
   * @var EntityRepository<ClientRecord>
   */
  private EntityRepository $repository;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of 
   * the ClientRepository class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager The entity manager.
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager
  ) {
    $this->repository = $entityManager->getRepository(className: ClientRecord::class);
  }
  //#endregion

  //#region Methods
  /**
   * Method save
   * {@inheritDoc}
   *
   * Saves a client.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Client $client The client to save.
   *
   * @return void No return value.
   */
  public function save(Client $client): void
  {
    $record = ClientMapper::toRecord(client: $client);

    // Check if record already exists
    $existingRecord = $this->repository->find(id: $record->id);

    if ($existingRecord) {
      // Update existing record properties
      $existingRecord->name = $record->name;
      $existingRecord->secret = $record->secret;
      $existingRecord->redirectUris = $record->redirectUris;
      $existingRecord->grantTypes = $record->grantTypes;
      $existingRecord->scopes = $record->scopes;
      $existingRecord->isActive = $record->isActive;
      // CreatedAt should not change
    } else {
      // Persist new record
      $this->entityManager->persist(object: $record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById
   * {@inheritDoc}
   *
   * Finds a client by its ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientId $id The client ID.
   *
   * @return Client|null The client or null if not found.
   */
  public function findById(ClientId $id): ?Client
  {
    $record = $this->repository->find(id: $id->value);

    if (!$record) {
      return null;
    }

    return ClientMapper::toDomain(record: $record);
  }

  /**
   * Method existsByName
   * {@inheritDoc}
   *
   * Checks if a client with the given name already exists.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientName $name The client name.
   *
   * @return bool True if exists, false otherwise.
   */
  public function existsByName(ClientName $name): bool
  {
    $count = $this->repository->count(criteria: ['name' => $name->value]);

    return $count > 0;
  }

  /**
   * Method delete
   * {@inheritDoc}
   *
   * Deletes a client.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Client $client The client to delete.
   *
   * @return void No return value.
   */
  public function delete(Client $client): void
  {
    $record = $this->repository->find(id: $client->id()->value);

    if ($record) {
      $this->entityManager->remove(object: $record);
      $this->entityManager->flush();
    }
  }

  /**
   * Method findAll
   * {@inheritDoc}
   *
   * Finds all clients with pagination.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $offset The offset.
   * @param int $limit The limit.
   *
   * @return array<Client> The clients.
   */
  public function findAll(int $offset = 0, int $limit = 20): array
  {
    $records = $this->repository->findBy(
      criteria: [],
      orderBy: ['createdAt' => 'DESC'],
      limit: $limit,
      offset: $offset
    );

    return array_map(
      callback: fn(ClientRecord $record): Client => ClientMapper::toDomain(record: $record),
      array: $records
    );
  }

  /**
   * Method count
   * {@inheritDoc}
   *
   * Counts the total number of clients.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int The total count.
   */
  public function count(): int
  {
    return $this->repository->count([]);
  }
  //#endregion
}
