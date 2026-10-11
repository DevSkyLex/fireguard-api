<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Assistant\Application\UseCase\Command\Thread\StartAssistantThread\{StartAssistantThreadCommand, StartAssistantThreadResult};
use Assistant\Presentation\Api\Dto\Input\StartAssistantThreadInput;
use Assistant\Presentation\Api\Dto\Output\AssistantThreadOutput;
use Assistant\Presentation\Api\Factory\AssistantThreadOutputFactory;
use Auth\Application\Contract\User\AuthenticatedUser;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/**
 * Processor StartAssistantThreadProcessor.
 *
 * Thin: authorization is self-enforced by
 * {@see \Assistant\Application\UseCase\Command\Thread\StartAssistantThread\StartAssistantThreadHandler}.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<StartAssistantThreadInput, AssistantThreadOutput>
 */
final readonly class StartAssistantThreadProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   * @param AssistantThreadOutputFactory $outputFactory the output factory
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    private AssistantThreadOutputFactory $outputFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AssistantThreadOutput
  {
    /** @var StartAssistantThreadInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    /** @var StartAssistantThreadResult $result */
    $result = $this->commandBus->dispatch(new StartAssistantThreadCommand(
      organizationId: $organizationId,
      actorUserId: $user->getId(),
      title: $data->title,
      model: $data->model,
    ));

    return $this->outputFactory->fromView($result->thread);
  }
  // #endregion
}
