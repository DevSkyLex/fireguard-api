<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use OAuth\Application\Port\Outbound\ClientRepositoryPort;
use OAuth\Application\Port\Outbound\LeagueClientRepositoryPort;
use OAuth\Domain\Model\Client;
use OAuth\Domain\ValueObject\ClientId;
use OAuth\Domain\ValueObject\ClientName;
use OAuth\Infrastructure\Persistence\Doctrine\Mapper\ClientMapper;
use OAuth\Infrastructure\Persistence\Doctrine\Record\ClientRecord;

use function array_map;

/**
 * Repository ClientRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientRepository implements ClientRepositoryPort, LeagueClientRepositoryPort
{
    // #region Properties
    /**
     * Property repository.
     *
     * The Doctrine entity repository
     * for ClientRecord.
     *
     * @since 1.0.0
     *
     * @var EntityRepository<ClientRecord>
     */
    private EntityRepository $repository;
    // #endregion

    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of
     * the ClientRepository class.
     *
     * @since 1.0.0
     *
     * @param EntityManagerInterface $entityManager the entity manager
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(className: ClientRecord::class);
    }
    // #endregion

    // #region Methods
    /**
     * Method save
     * {@inheritDoc}
     *
     * Saves a client.
     *
     * @since 1.0.0
     *
     * @param Client $client the client to save
     *
     * @return void no return value
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
     * @since 1.0.0
     *
     * @param ClientId $id the client ID
     *
     * @return Client|null the client or null if not found
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
     * @since 1.0.0
     *
     * @param ClientName $name the client name
     *
     * @return bool true if exists, false otherwise
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
     * @since 1.0.0
     *
     * @param Client $client the client to delete
     *
     * @return void no return value
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
     * @since 1.0.0
     *
     * @param int $offset the offset
     * @param int $limit  the limit
     *
     * @return array<Client> the clients
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
            callback: fn (ClientRecord $record): Client => ClientMapper::toDomain(record: $record),
            array: $records
        );
    }

    /**
     * Method count
     * {@inheritDoc}
     *
     * Counts the total number of clients.
     *
     * @since 1.0.0
     *
     * @return int the total count
     */
    public function count(): int
    {
        return $this->repository->count([]);
    }

    /**
     * Method find
     * {@inheritDoc}
     *
     * Finds a client by its OAuth identifier.
     *
     * @since 1.0.0
     *
     * @param \OAuth\Domain\ValueObject\OAuthClientIdentifier $identifier the client identifier
     *
     * @return \OAuth\Domain\Model\LeagueClient|null the client or null if not found
     */
    public function find(\OAuth\Domain\ValueObject\OAuthClientIdentifier $identifier): ?\OAuth\Domain\Model\LeagueClient
    {
        // Assume identifier is UUID
        $record = $this->repository->find(id: $identifier->value);

        if (!$record) {
            return null;
        }

        return ClientMapper::toLeague(record: $record);
    }
    // #endregion
}
