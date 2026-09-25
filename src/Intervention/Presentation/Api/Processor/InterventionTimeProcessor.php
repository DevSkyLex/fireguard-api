<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\{Delete, Operation, Post};
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Command\Time\WriteTimeEntry\{WriteTimeEntryCommand, WriteTimeEntryResult};
use Intervention\Presentation\Api\Dto\Input\WriteTimeEntryInput;
use Intervention\Presentation\Api\Dto\Output\TimeEntryOutput;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\RevisionGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * InterventionTimeProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, TimeEntryOutput|null>
 */
final readonly class InterventionTimeProcessor implements ProcessorInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  /**
   * @since 1.0.0
   *
   * @param CommandBusPort $commands dispatches write use cases through the command bus
   * @param Security $security resolves the authenticated account at the HTTP boundary
   * @param RevisionGuard $revisions resolves the expected resource revision from the request
   */
  public function __construct(private CommandBusPort $commands, private Security $security, private RevisionGuard $revisions)
  {
  }

  /**
   * Translates journal writes and revision preconditions into the independent time use case.
   *
   * @since 1.0.0
   *
   * @param mixed $data deserialized API input; authorization remains in the use case
   * @param Operation $operation API operation metadata
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   *
   * @return ?TimeEntryOutput updated journal entry, or null for a cancellation response
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?TimeEntryOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    if ($operation instanceof Post) {
      $action = 'create';
    } elseif ($operation instanceof Delete) {
      $action = 'cancel';
    } else {
      $action = 'correct';
    }
    if ('cancel' !== $action && !$data instanceof WriteTimeEntryInput) {
      throw new BadRequestHttpException('A time entry is required.');
    }
    $input = $data instanceof WriteTimeEntryInput ? $data : null;
    $taskId = is_string($uriVariables['taskId'] ?? null) ? $uriVariables['taskId'] : '';
    $id = is_string($uriVariables['entryId'] ?? null) ? $uriVariables['entryId'] : $input?->id;

    try {
      /** @var WriteTimeEntryResult $result */
      $result = $this->commands->dispatch(new WriteTimeEntryCommand(
        $user->getId(),
        $taskId,
        $action,
        $id,
        $input?->memberId,
        $input?->workedOn,
        $input?->minutes,
        $input?->note,
        'create' === $action ? null : $this->revisions->expectedRevision(),
      ));
    } catch (Throwable $error) {
      throw $this->mapWorkflowException($error);
    }

    return 'cancel' === $action ? null : new TimeEntryOutput($result->entry->id, $result->entry);
  }
}
