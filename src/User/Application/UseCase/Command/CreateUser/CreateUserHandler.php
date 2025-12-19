<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\CreateUser;

use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Application\Port\Outbound\HashingPort;
use Shared\Domain\Service\EventIdProvider;
use Shared\Domain\ValueObject\Email;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User;
use User\Domain\ValueObject\HashedPassword;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\Username;
use User\Domain\ValueObject\UserProfile;

/**
 * Handler CreateUserHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateUserHandler implements \Shared\Application\Message\CommandHandler
{
    // #region Constructor
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param UserRepositoryPort $userRepository  the user repository
     * @param UuidFactory        $uuidFactory     the UUID factory
     * @param HashingPort        $hashing         the hashing service
     * @param EventBusPort       $eventBus        the event bus
     * @param EventIdProvider    $eventIdProvider the event ID provider
     */
    public function __construct(
        private readonly UserRepositoryPort $userRepository,
        private readonly UuidFactory $uuidFactory,
        private readonly HashingPort $hashing,
        private readonly EventBusPort $eventBus,
        private readonly EventIdProvider $eventIdProvider,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method __invoke.
     *
     * Handles the command.
     *
     * @since 1.0.0
     *
     * @param CreateUserCommand $command the command
     */
    public function __invoke(CreateUserCommand $command): CreateUserResult
    {
        // Generate user ID using factory
        $userId = $this->uuidFactory->create(UserId::class);

        // Hash the password
        $hashedPassword = new HashedPassword(
            value: $this->hashing->hash(value: $command->password)->value
        );

        // Create the user with event ID provider
        $user = User::register(
            id: $userId,
            username: new Username(value: $command->username),
            email: new Email(value: $command->email),
            password: $hashedPassword,
            profile: new UserProfile(
                firstName: $command->firstName,
                lastName: $command->lastName,
                avatarUrl: $command->avatarUrl,
            ),
            eventIdProvider: $this->eventIdProvider,
            tenantId: null, // TODO: Handle tenant ID
        );

        // Save the user
        $this->userRepository->save(user: $user);

        // Publish domain events
        foreach ($user->releaseEvents() as $event) {
            $this->eventBus->publish(event: $event);
        }

        return new CreateUserResult(userId: $userId->value);
    }
    // #endregion
}
