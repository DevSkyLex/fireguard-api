<?php

declare(strict_types=1);

namespace OAuth\Domain\Model\Client;

use DateTimeImmutable;
use OAuth\Domain\Event\Client\{ClientActivatedEvent, ClientDeactivatedEvent, ClientDeletedEvent, ClientRegisteredEvent, ClientSecretRegeneratedEvent, ClientUpdatedEvent};
use OAuth\Domain\ValueObject\Client\{ClientId, ClientName, ClientSecret, RedirectUri};
use OAuth\Domain\ValueObject\Scope\{Scope, Scopes};
use OAuth\Domain\ValueObject\Security\{GrantType, GrantTypes};
use Shared\Domain\Service\EventIdProvider;
use Shared\Domain\Trait\RecordsDomainEvents;

use function array_map;
use function array_values;
use function in_array;

/**
 * Model Client.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Client
{
  // #region Traits
  /**
   * Trait RecordsDomainEvents.
   *
   * Records domain events
   * for the Client entity.
   *
   * @since 1.0.0
   * @see RecordsDomainEvents
   */
  use RecordsDomainEvents;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Private constructor to enforce
   * factory method usage.
   *
   * @since 1.0.0
   *
   * @param ClientId $id the client ID
   * @param ClientName $name the client name
   * @param ClientSecret $secret the hashed client secret
   * @param list<string> $redirectUris the allowed redirect URIs
   * @param GrantTypes $grantTypes the allowed grant types
   * @param Scopes $scopes the allowed scopes
   * @param bool $isActive whether the client is active
   * @param DateTimeImmutable $createdAt the creation timestamp
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
    private ?DateTimeImmutable $deletedAt = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method register.
   *
   * @static
   *
   * Registers a new OAuth client.
   *
   * @since 1.0.0
   *
   * @param ClientId $id the client ID
   * @param ClientName $name the client name
   * @param ClientSecret $secret the hashed client secret
   * @param array<RedirectUri> $redirectUris the allowed redirect URIs
   * @param GrantTypes $grantTypes the allowed grant types
   * @param Scopes $scopes the allowed scopes
   * @param EventIdProvider $eventIdProvider the event ID provider
   *
   * @return self the new Client instance
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
      redirectUris: array_values(array_map(fn (RedirectUri $uri) => $uri->value, $redirectUris)),
      grantTypes: $grantTypes,
      scopes: $scopes,
      isActive: true,
      createdAt: new DateTimeImmutable(),
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
   * Method id.
   *
   * Returns the client ID.
   *
   * @since 1.0.0
   *
   * @return ClientId the client ID
   */
  public function id(): ClientId
  {
    return $this->id;
  }

  /**
   * Method name.
   *
   * Returns the client name.
   *
   * @since 1.0.0
   *
   * @return ClientName the client name
   */
  public function name(): ClientName
  {
    return $this->name;
  }

  /**
   * Method secret.
   *
   * Returns the hashed client secret.
   *
   * @since 1.0.0
   *
   * @return ClientSecret the hashed client secret
   */
  public function secret(): ClientSecret
  {
    return $this->secret;
  }

  /**
   * Method redirectUris.
   *
   * Returns the allowed redirect URIs.
   *
   * @since 1.0.0
   *
   * @return list<string> the redirect URIs
   */
  public function redirectUris(): array
  {
    return $this->redirectUris;
  }

  /**
   * Method grantTypes.
   *
   * Returns the allowed grant types.
   *
   * @since 1.0.0
   *
   * @return GrantTypes the grant types
   */
  public function grantTypes(): GrantTypes
  {
    return $this->grantTypes;
  }

  /**
   * Method scopes.
   *
   * Returns the allowed scopes.
   *
   * @since 1.0.0
   *
   * @return Scopes the scopes
   */
  public function scopes(): Scopes
  {
    return $this->scopes;
  }

  /**
   * Method isActive.
   *
   * Returns whether the client is active.
   *
   * @since 1.0.0
   *
   * @return bool true if active, false otherwise
   */
  public function isActive(): bool
  {
    return $this->isActive;
  }

  /**
   * Method createdAt.
   *
   * Returns the creation timestamp.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the creation timestamp
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method deletedAt.
   *
   * Returns the deletion timestamp.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable|null the deletion timestamp or null if not deleted
   */
  public function deletedAt(): ?DateTimeImmutable
  {
    return $this->deletedAt;
  }

  /**
   * Method isDeleted.
   *
   * Returns whether the client is deleted.
   *
   * @since 1.0.0
   *
   * @return bool true if deleted, false otherwise
   */
  public function isDeleted(): bool
  {
    return null !== $this->deletedAt;
  }

  /**
   * Method validateRedirectUri.
   *
   * Validates if a redirect URI is allowed for this client.
   *
   * @since 1.0.0
   *
   * @param RedirectUri $uri the redirect URI to validate
   *
   * @return bool true if the URI is allowed, false otherwise
   */
  public function validateRedirectUri(RedirectUri $uri): bool
  {
    return in_array(needle: $uri->value, haystack: $this->redirectUris, strict: true);
  }

  /**
   * Method supportsGrantType.
   *
   * Checks if the client supports a specific grant type.
   *
   * @since 1.0.0
   *
   * @param GrantType $grantType the grant type to check
   *
   * @return bool true if supported, false otherwise
   */
  public function supportsGrantType(GrantType $grantType): bool
  {
    return $this->grantTypes->contains($grantType);
  }

  /**
   * Method hasScope.
   *
   * Checks if the client has a specific scope.
   *
   * @since 1.0.0
   *
   * @param Scope $scope the scope to check
   *
   * @return bool true if the client has the scope, false otherwise
   */
  public function hasScope(Scope $scope): bool
  {
    return $this->scopes->contains($scope);
  }

  /**
   * Method activate.
   *
   * Activates the client.
   *
   * @since 1.0.0
   *
   * @param EventIdProvider $eventIdProvider the event ID provider
   *
   * @return void no return value
   */
  public function activate(EventIdProvider $eventIdProvider): void
  {
    if ($this->isActive) {
      return;
    }

    $this->isActive = true;

    $this->recordEvent(new ClientActivatedEvent(
      eventId: $eventIdProvider->nextEventId(),
      clientId: $this->id,
      occurredAt: new DateTimeImmutable(),
    ));
  }

  /**
   * Method deactivate.
   *
   * Deactivates the client.
   *
   * @since 1.0.0
   *
   * @param EventIdProvider $eventIdProvider the event ID provider
   *
   * @return void no return value
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
   * Method regenerateSecret.
   *
   * Regenerates the client secret.
   *
   * @since 1.0.0
   *
   * @param ClientSecret $newSecret the new hashed secret
   * @param EventIdProvider $eventIdProvider the event ID provider
   *
   * @return void no return value
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
   * Method updateDetails.
   *
   * Updates the client details.
   *
   * @since 1.0.0
   *
   * @param ClientName $name the new client name
   * @param array<RedirectUri> $redirectUris the new redirect URIs
   * @param Scopes $scopes the new scopes
   * @param EventIdProvider $eventIdProvider the event ID provider
   *
   * @return void no return value
   */
  public function updateDetails(
    ClientName $name,
    array $redirectUris,
    Scopes $scopes,
    EventIdProvider $eventIdProvider,
  ): void {
    $this->name = $name;
    $this->redirectUris = array_values(array_map(fn (RedirectUri $uri) => $uri->value, $redirectUris));
    $this->scopes = $scopes;

    $this->recordEvent(new ClientUpdatedEvent(
      eventId: $eventIdProvider->nextEventId(),
      clientId: $this->id,
      name: $name,
      occurredAt: new DateTimeImmutable(),
    ));
  }

  /**
   * Method delete.
   *
   * Soft deletes the client.
   *
   * @since 1.0.0
   *
   * @param EventIdProvider $eventIdProvider the event ID provider
   *
   * @return void no return value
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
  // #endregion
}
