<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Command\Label\CreateInterventionLabel\{CreateInterventionLabelCommand, CreateInterventionLabelResult};
use Intervention\Application\UseCase\Command\Label\DeleteInterventionLabel\DeleteInterventionLabelCommand;
use Intervention\Application\UseCase\Command\Label\UpdateInterventionLabel\{UpdateInterventionLabelCommand, UpdateInterventionLabelResult};
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionLabelInput, UpdateInterventionLabelInput};
use Intervention\Presentation\Api\Dto\Output\InterventionLabelOutput;
use Intervention\Presentation\Api\Factory\InterventionLabelOutputFactory;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\{MergePatchFields, ResourceIriParser};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_key_exists;
use function is_string;

/**
 * Processor InterventionLabelProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, InterventionLabelOutput|null>
 */
final readonly class InterventionLabelProcessor implements ProcessorInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionLabelProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param InterventionLabelOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private InterventionLabelOutputFactory $mapper,
    private Security $security,
    private MergePatchFields $mergePatchFields,
  ) {
  }

  /**
   * Method process.
   *
   * Executes the process operation.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return ?InterventionLabelOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InterventionLabelOutput
  {
    $user = $this->user();
    $id = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : null;

    try {
      if ($data instanceof CreateInterventionLabelInput) {
        /** @var CreateInterventionLabelResult $result */
        $result = $this->commandBus->dispatch(new CreateInterventionLabelCommand(
          userId: $user->getId(),
          organizationId: ResourceIriParser::id($data->organization, 'organizations'),
          name: $data->name,
          color: $data->color,
        ));

        return $this->mapper->fromView($result->label);
      }

      if ($data instanceof UpdateInterventionLabelInput) {
        if (null === $id) {
          throw new BadRequestHttpException('The id URI parameter is required.');
        }
        $fields = $this->mergePatchFields->all();
        /** @var UpdateInterventionLabelResult $result */
        $result = $this->commandBus->dispatch(new UpdateInterventionLabelCommand(
          userId: $user->getId(),
          labelId: $id,
          name: $data->name,
          color: $data->color,
          hasName: array_key_exists('name', $fields),
          hasColor: array_key_exists('color', $fields),
        ));

        return $this->mapper->fromView($result->label);
      }

      if (null === $id) {
        throw new BadRequestHttpException('The id URI parameter is required.');
      }
      $this->commandBus->dispatch(new DeleteInterventionLabelCommand($user->getId(), $id));

      return null;
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }
  }

  /**
   * Method user.
   *
   * Executes the user operation.
   *
   * @since 1.0.0
   *
   * @return SecurityUser the user result
   */
  private function user(): SecurityUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
}
