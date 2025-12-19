<?php

declare(strict_types=1);

namespace User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use User\Application\UseCase\Command\DeleteUser\DeleteUserCommand;

use function is_string;

/**
 * Processor DeleteUserProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeleteUserProcessor implements ProcessorInterface
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * DeleteUserProcessor class.
     *
     * @since 1.0.0
     *
     * @param CommandBusPort $commandBus the command bus
     */
    public function __construct(
        private readonly CommandBusPort $commandBus,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method process.
     *
     * Processes the delete user request.
     *
     * @since 1.0.0
     *
     * @param mixed                $data         the input data
     * @param Operation            $operation    the operation
     * @param array<string, mixed> $uriVariables the URI variables
     * @param array<string, mixed> $context      the context
     *
     * @return void no return value
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $id = $uriVariables['id'] ?? null;
        if (!is_string($id)) {
            return;
        }

        $command = new DeleteUserCommand(id: $id);
        $this->commandBus->dispatch(command: $command);
    }
    // #endregion
}
