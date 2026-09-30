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
 * Class FederatedUserService
 *
 * Owns creation and credential changes for users authenticated by an external
 * identity provider while publishing only an application-level contract.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedUserService implements FederatedUserPort
{
  // #region Constants
  /**
   * Constant MAX_USERNAME_ATTEMPTS
   *
   * Maximum collision-check attempts with random suffixes before the final fallback.
   *
   * @access private
   *
   * @var int
   */
  private const int MAX_USERNAME_ATTEMPTS = 10;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Binds user ownership, identity generation, password hashing and post-save event publication.
   *
   * @access public
   *
   * @param UserRepositoryPort $users port owning local user persistence
   * @param UuidFactory $uuidFactory factory for newly provisioned user identifiers
   * @param HashingPort $hashing hashes the initial local password
   * @param EventBusPort $eventBus publishes domain events after provisioning is persisted
   * @param EventIdProvider $eventIdProvider supplies identities for events produced by the new aggregate
   *
   * @return void
   */
  public function __construct(
    private UserRepositoryPort $users,
    private UuidFactory $uuidFactory,
    private HashingPort $hashing,
    private EventBusPort $eventBus,
    private EventIdProvider $eventIdProvider,
  ) {
  }
  // #endregion

  // #region Methods

  /**
   * Method find
   *
   * Returns the authentication-facing projection of a user.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId local user identifier to resolve
   *
   * @return ?FederatedUser the projection, or null when the user is absent
   */
  public function find(string $userId): ?FederatedUser
  {
    $user = $this->users->findById(new UserId($userId));

    return null === $user ? null : $this->toContract($user);
  }

  /**
   * Method provision
   *
   * Creates an active, verified user when the provider email is still available, then publishes events after saving.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $email provider email normalized to lowercase before the availability check
   * @param string $firstName provider first name; empty text uses the email local part
   * @param string $lastName provider last name retained in the local profile
   *
   * @return ?FederatedUser the created projection, or null when the email is already registered
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
   * Method recordSuccessfulLogin
   *
   * Records a successful federated login after enforcing account status.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId local user identifier whose login is recorded
   *
   * @return ?FederatedUser the updated projection, or null when absent or unable to sign in
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
   * Method recordSignInMethod
   *
   * Persists the method only after the Fireguard session is complete.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId local user identifier whose account must allow sign-in
   * @param string $method sign-in method identifier retained by the user aggregate
   *
   * @return bool whether the eligible user was found and updated
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
   * Method setInitialPassword
   *
   * Configures a local password exactly once for a federated-only account.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId local user identifier whose password is not yet configured
   * @param string $plainPassword plaintext password passed to the hashing port; never returned
   *
   * @return bool whether the existing account received its initial password
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

  /**
   * Method toContract
   *
   * Projects account identity and sign-in eligibility through the published User boundary.
   *
   * @access private
   *
   * @param User $user aggregate whose authentication-facing state is exposed
   * @param bool $created whether this projection comes from provisioning a new account
   *
   * @return FederatedUser the published authentication projection
   */
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

  /**
   * Method deriveUniqueUsername
   *
   * Sanitizes and bounds an email-derived username, tries random suffixes on collisions and finally returns a random fallback.
   *
   * @access private
   *
   * @param string $email normalized email used as the username seed
   *
   * @return Username the available candidate, or the unchecked random fallback after retry exhaustion
   */
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

  /**
   * Method fallbackName
   *
   * Extracts the email local part and uses the default user seed when no non-empty local part is available.
   *
   * @access private
   *
   * @param string $email email address from which to derive a display or username seed
   *
   * @return string the local part or default user seed
   */
  private function fallbackName(string $email): string
  {
    $localPart = strstr($email, '@', true);

    return false === $localPart || '' === $localPart ? 'user' : $localPart;
  }
  // #endregion
}
