<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

use OAuth\Domain\Model\Client;
use OAuth\Domain\ValueObject\ClientId;
use OAuth\Domain\ValueObject\ClientName;

/**
 * Port ClientRepositoryPort
 *
 * Port for persisting and retrieving OAuth clients.
 *
 * @category Outbound Port
 * @package OAuth\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ClientRepositoryPort
{
  //#region Methods
  /**
   * Method save
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
  public function save(Client $client): void;

  /**
   * Method findById
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
  public function findById(ClientId $id): ?Client;

  /**
   * Method existsByName
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
  public function existsByName(ClientName $name): bool;

  /**
   * Method delete
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
  public function delete(Client $client): void;

  /**
   * Method findAll
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
  public function findAll(int $offset = 0, int $limit = 20): array;

  /**
   * Method count
   *
   * Counts the total number of clients.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int The total count.
   */
  public function count(): int;
  //#endregion
}
