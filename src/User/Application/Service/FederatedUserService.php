<?php

declare(strict_types=1);

namespace User\Application\Service;

use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{EventBusPort, HashingPort};
use Shared\Domain\Service\EventIdProvider;
use Shared\Domain\ValueObject\Email;
use User\Application\Contract\Federation\FederatedUser;
use User\Application\Port\Inbound\FederatedUserPort;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

use function preg_replace;
use function random_int;
use function str_pad;
use function strlen;
use function strstr;
use function strtolower;
use function substr;

use const STR_PAD_RIGHT;

/**
 * Service FederatedUserService.
 *
 * Owns creation and credential changes for users authenticated by an external
 * identity provider while publishing only an application-level contract.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedUserService implements FederatedUserPort
{
  private const int MAX_USERNAME_ATTEMPTS = 10;

  public function __construct(
    private UserRepositoryPort $users,
    private UuidFactory $uuidFactory,
    private HashingPort $hashing,
    private EventBusPort $eventBus,
    private EventIdProvider $eventIdProvider,
  ) {
  }

  /**
   * Returns the authentication-facing projection of a user.
   *
   * @since 1.0.0
   */
  public function find(string $userId): ?FederatedUser
  {
    $user = $this->users->findById(new UserId($userId));

    return null === $user ? null : $this->toContract($user);
  }

  /**
   * Creates an active, verified user when the provider email is still available.
   *
   * @since 1.0.0
   */
  public function provision(string $email, string $firstName, string $lastName): ?FederatedUser
  {
    $normalizedEmail = new Email(strtolower($email));
    if ($this->users->existsByEmail($normalizedEmail)) {
      return null;
    }

    $user = User::registerFederated(
      id: $this->uuidFactory->create(UserId::class),
      username: $this->deriveUniqueUsername($normalizedEmail->value),
      email: $normalizedEmail,
      profile: new UserProfile(
        firstName: '' === $firstName ? $this->fallbackName($normalizedEmail->value) : $firstName,
        lastName: $lastName,
        avatarUrl: null,
      ),
      eventIdProvider: $this->eventIdProvider,
    );

    $this->users->save($user);
    foreach ($user->releaseEvents() as $event) {
      $this->eventBus->publish($event);
    }

    return $this->toContract($user, true);
  }

  /**
   * Records a successful federated login after enforcing account status.
   *
   * @since 1.0.0
   */
  public function recordSuccessfulLogin(string $userId): ?FederatedUser
  {
    $user = $this->users->findById(new UserId($userId));
    if (null === $user || !$user->canLogin()) {
      return null;
    }

    $user->recordSuccessfulLogin();
    $this->users->save($user);

    return $this->toContract($user);
  }

  /**
   * Persists the method only after the Fireguard session is complete.
   *
   * @since 1.0.0
   */
  public function recordSignInMethod(string $userId, string $method): bool
  {
    $user = $this->users->findById(new UserId($userId));
    if (null === $user || !$user->canLogin()) {
      return false;
    }

    $user->recordSignInMethod($method);
    $this->users->save($user);

    return true;
  }

  /**
   * Configures a local password exactly once for a federated-only account.
   *
   * @since 1.0.0
   */
  public function setInitialPassword(string $userId, string $plainPassword): bool
  {
    $user = $this->users->findById(new UserId($userId));
    if (null === $user || $user->hasPassword()) {
      return false;
    }

    $user->changePassword(new HashedPassword($this->hashing->hash($plainPassword)->value));
    $this->users->save($user);

    return true;
  }

  private function toContract(User $user, bool $created = false): FederatedUser
  {
    return new FederatedUser(
      userId: $user->id()->value,
      email: $user->email()->value,
      canLogin: $user->canLogin(),
      passwordConfigured: $user->hasPassword(),
      created: $created,
      lastSignInMethod: $user->lastSignInMethod(),
    );
  }

  private function deriveUniqueUsername(string $email): Username
  {
    $base = preg_replace('/[^a-z0-9_-]/', '', strtolower($this->fallbackName($email))) ?? '';
    $base = strlen($base) < 3 ? str_pad($base, 3, '0', STR_PAD_RIGHT) : substr($base, 0, 44);

    if (!$this->users->existsByUsername(new Username($base))) {
      return new Username($base);
    }

    for ($attempt = 0; $attempt < self::MAX_USERNAME_ATTEMPTS; ++$attempt) {
      $suffix = (string) random_int(1000, 999999);
      $candidate = substr($base, 0, 50 - strlen($suffix) - 1) . '-' . $suffix;
      if (!$this->users->existsByUsername(new Username($candidate))) {
        return new Username($candidate);
      }
    }

    return new Username('user-' . random_int(100000000, 999999999));
  }

  private function fallbackName(string $email): string
  {
    $localPart = strstr($email, '@', true);

    return false === $localPart || '' === $localPart ? 'user' : $localPart;
  }
}
