<?php

declare(strict_types=1);

namespace Client\Domain\Model;

use Client\Domain\Event\ClientRegisteredEvent;
use Client\Domain\Event\ClientActivatedEvent;
use Client\Domain\Event\ClientDeactivatedEvent;
use Client\Domain\Event\ClientSecretRegeneratedEvent;
use Client\Domain\Event\ClientUpdatedEvent;
use Client\Domain\Event\ClientDeletedEvent;
use Client\Domain\ValueObject\ClientId;
use Client\Domain\ValueObject\ClientName;
use Client\Domain\ValueObject\ClientSecret;
use Shared\Domain\Service\EventIdProvider;
use Shared\Domain\Trait\RecordsDomainEvents;
use Shared\Domain\ValueObject\GrantType;
use Shared\Domain\ValueObject\GrantTypes;
use Shared\Domain\ValueObject\RedirectUri;
use Shared\Domain\ValueObject\Scope;
use Shared\Domain\ValueObject\Scopes;
use DateTimeImmutable;

/**
 * Model Client
 * @final
 *
 * Represents an OAuth 2.0 client application.
 * This is the aggregate root for the Client bounded context.
 *
 * @category Model
 * @package Client\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Client
{
  //#region Traits
  /**
   * Trait RecordsDomainEvents
   *
   * Records domain events for the Client entity.
   *
   * @since 1.0.0
   * @see RecordsDomainEvents
   */
  use RecordsDomainEvents;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Private constructor to enforce factory method usage.
   *
   * @access private
   * @since 1.0.0
   *
   * @param ClientId $id The client ID.
   * @param ClientName $name The client name.
   * @param ClientSecret $secret The hashed client secret.
   * @param list<string> $redirectUris The allowed redirect URIs.
   * @param GrantTypes $grantTypes The allowed grant types.
   * @param Scopes $scopes The allowed scopes.
   * @param bool $isActive Whether the client is active.
   * @param DateTimeImmutable $createdAt The creation timestamp.
   */
  private function __construct(
    private ClientId $id,
    private ClientName $name,
    private ClientSecret $secret,
    private array $redirectUris,
    private GrantTypes $grantTypes,
    private Scopes $scopes,
    private bool $isActive,
    private DateTimeImmutable $createdAt,
    private ?DateTimeImmutable $deletedAt = null
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method register
   * @static
   *
   * Registers a new OAuth client.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientId $id The client ID.
   * @param ClientName $name The client name.
   * @param ClientSecret $secret The hashed client secret.
   * @param array<RedirectUri> $redirectUris The allowed redirect URIs.
   * @param GrantTypes $grantTypes The allowed grant types.
   * @param Scopes $scopes The allowed scopes.
   * @param EventIdProvider $eventIdProvider The event ID provider.
   *
   * @return self The new Client instance.
   */
  public static function register(
    ClientId $id,
    ClientName $name,
    ClientSecret $secret,
    array $redirectUris,
    GrantTypes $grantTypes,
    Scopes $scopes,
    EventIdProvider $eventIdProvider,
  ): self {
    $client = new self(
      id: $id,
      name: $name,
      secret: $secret,
      redirectUris: array_values(array_map(fn(RedirectUri $uri) => $uri->value, $redirectUris)),
      grantTypes: $grantTypes,
      scopes: $scopes,
      isActive: true,
      createdAt: new DateTimeImmutable()
    );

    $client->recordEvent(new ClientRegisteredEvent(
      eventId: $eventIdProvider->nextEventId(),
      clientId: $id,
      name: $name,
      occurredAt: $client->createdAt,
    ));

    return $client;
  }

  /**
   * Method id
   *
   * Returns the client ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return ClientId The client ID.
   */
  public function id(): ClientId
  {
    return $this->id;
  }

  /**
   * Method name
   *
   * Returns the client name.
   *
   * @access public
   * @since 1.0.0
   *
   * @return ClientName The client name.
   */
  public function name(): ClientName
  {
    return $this->name;
  }

  /**
   * Method secret
   *
   * Returns the hashed client secret.
   *
   * @access public
   * @since 1.0.0
   *
   * @return ClientSecret The hashed client secret.
   */
  public function secret(): ClientSecret
  {
    return $this->secret;
  }

  /**
   * Method redirectUris
   *
   * Returns the allowed redirect URIs.
   *
   * @access public
   * @since 1.0.0
   *
   * @return list<string> The redirect URIs.
   */
  public function redirectUris(): array
  {
    return $this->redirectUris;
  }

  /**
   * Method grantTypes
   *
   * Returns the allowed grant types.
   *
   * @access public
   * @since 1.0.0
   *
   * @return GrantTypes The grant types.
   */
  public function grantTypes(): GrantTypes
  {
    return $this->grantTypes;
  }

  /**
   * Method scopes
   *
   * Returns the allowed scopes.
   *
   * @access public
   * @since 1.0.0
   *
   * @return Scopes The scopes.
   */
  public function scopes(): Scopes
  {
    return $this->scopes;
  }

  /**
   * Method isActive
   *
   * Returns whether the client is active.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if active, false otherwise.
   */
  public function isActive(): bool
  {
    return $this->isActive;
  }

  /**
   * Method createdAt
   *
   * Returns the creation timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable The creation timestamp.
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method deletedAt
   *
   * Returns the deletion timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable|null The deletion timestamp or null if not deleted.
   */
  public function deletedAt(): ?DateTimeImmutable
  {
    return $this->deletedAt;
  }

  /**
   * Method isDeleted
   *
   * Returns whether the client is deleted.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if deleted, false otherwise.
   */
  public function isDeleted(): bool
  {
    return $this->deletedAt !== null;
  }

  /**
   * Method validateRedirectUri
   *
   * Validates if a redirect URI is allowed for this client.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RedirectUri $uri The redirect URI to validate.
   *
   * @return bool True if the URI is allowed, false otherwise.
   */
  public function validateRedirectUri(RedirectUri $uri): bool
  {
    return in_array(needle: $uri->value, haystack: $this->redirectUris, strict: true);
  }

  /**
   * Method supportsGrantType
   *
   * Checks if the client supports a specific grant type.
   *
   * @access public
   * @since 1.0.0
   *
   * @param GrantType $grantType The grant type to check.
   *
   * @return bool True if supported, false otherwise.
   */
  public function supportsGrantType(GrantType $grantType): bool
  {
    return $this->grantTypes->contains($grantType);
  }

  /**
   * Method hasScope
   *
   * Checks if the client has a specific scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Scope $scope The scope to check.
   *
   * @return bool True if the client has the scope, false otherwise.
   */
  public function hasScope(Scope $scope): bool
  {
    return $this->scopes->contains($scope);
  }

  /**
   * Method activate
   *
   * Activates the client.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EventIdProvider $eventIdProvider The event ID provider.
   *
   * @return void No return value.
   */
  public function activate(EventIdProvider $eventIdProvider): void
  {
    if ($this->isActive)
      return;

    $this->isActive = true;

    $this->recordEvent(new ClientActivatedEvent(
      eventId: $eventIdProvider->nextEventId(),
      clientId: $this->id,
      occurredAt: new DateTimeImmutable(),
    ));
  }

  /**
   * Method deactivate
   *
   * Deactivates the client.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EventIdProvider $eventIdProvider The event ID provider.
   *
   * @return void No return value.
   */
  public function deactivate(EventIdProvider $eventIdProvider): void
  {
    if (!$this->isActive) {
      return; // Already inactive
    }

    $this->isActive = false;

    $this->recordEvent(new ClientDeactivatedEvent(
      eventId: $eventIdProvider->nextEventId(),
      clientId: $this->id,
      occurredAt: new DateTimeImmutable(),
    ));
  }

  /**
   * Method regenerateSecret
   *
   * Regenerates the client secret.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientSecret $newSecret The new hashed secret.
   * @param EventIdProvider $eventIdProvider The event ID provider.
   *
   * @return void No return value.
   */
  public function regenerateSecret(ClientSecret $newSecret, EventIdProvider $eventIdProvider): void
  {
    $this->secret = $newSecret;

    $this->recordEvent(new ClientSecretRegeneratedEvent(
      eventId: $eventIdProvider->nextEventId(),
      clientId: $this->id,
      occurredAt: new DateTimeImmutable(),
    ));
  }

  /**
   * Method updateDetails
   *
   * Updates the client details.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientName $name The new client name.
   * @param array<RedirectUri> $redirectUris The new redirect URIs.
   * @param Scopes $scopes The new scopes.
   * @param EventIdProvider $eventIdProvider The event ID provider.
   *
   * @return void No return value.
   */
  public function updateDetails(
    ClientName $name,
    array $redirectUris,
    Scopes $scopes,
    EventIdProvider $eventIdProvider,
  ): void {
    $this->name = $name;
    $this->redirectUris = array_values(array_map(fn(RedirectUri $uri) => $uri->value, $redirectUris));
    $this->scopes = $scopes;

    $this->recordEvent(new ClientUpdatedEvent(
      eventId: $eventIdProvider->nextEventId(),
      clientId: $this->id,
      name: $name,
      occurredAt: new DateTimeImmutable(),
    ));
  }

  /**
   * Method delete
   *
   * Soft deletes the client.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EventIdProvider $eventIdProvider The event ID provider.
   *
   * @return void No return value.
   */
  public function delete(EventIdProvider $eventIdProvider): void
  {
    if ($this->isDeleted()) {
      return; // Already deleted
    }

    $this->deletedAt = new DateTimeImmutable();

    $this->recordEvent(new ClientDeletedEvent(
      eventId: $eventIdProvider->nextEventId(),
      clientId: $this->id,
      occurredAt: $this->deletedAt,
    ));
  }
  //#endregion
}
