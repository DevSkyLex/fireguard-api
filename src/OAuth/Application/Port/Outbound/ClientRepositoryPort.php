<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

use OAuth\Domain\Model\Client;
use OAuth\Domain\ValueObject\ClientId;
use OAuth\Domain\ValueObject\ClientName;

/**
 * Port ClientRepositoryPort.
 *
 * Port for persisting and retrieving OAuth clients.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ClientRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Saves a client.
   *
   * @since 1.0.0
   *
   * @param Client $client the client to save
   *
   * @return void no return value
   */
  public function save(Client $client): void;

  /**
   * Method findById.
   *
   * Finds a client by its ID.
   *
   * @since 1.0.0
   *
   * @param ClientId $id the client ID
   *
   * @return Client|null the client or null if not found
   */
  public function findById(ClientId $id): ?Client;

  /**
   * Method existsByName.
   *
   * Checks if a client with the given name already exists.
   *
   * @since 1.0.0
   *
   * @param ClientName $name the client name
   *
   * @return bool true if exists, false otherwise
   */
  public function existsByName(ClientName $name): bool;

  /**
   * Method delete.
   *
   * Deletes a client.
   *
   * @since 1.0.0
   *
   * @param Client $client the client to delete
   *
   * @return void no return value
   */
  public function delete(Client $client): void;

  /**
   * Method findAll.
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
  public function findAll(int $offset = 0, int $limit = 20): array;

  /**
   * Method count.
   *
   * Counts the total number of clients.
   *
   * @since 1.0.0
   *
   * @return int the total count
   */
  public function count(): int;
  // #endregion
}
