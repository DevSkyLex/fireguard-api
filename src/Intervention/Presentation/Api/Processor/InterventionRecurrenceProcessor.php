<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Intervention\Application\UseCase\Command\Recurrence\CreateInterventionRecurrence\{CreateInterventionRecurrenceCommand, CreateInterventionRecurrenceResult};
use Intervention\Application\UseCase\Command\Recurrence\DeleteInterventionRecurrence\DeleteInterventionRecurrenceCommand;
use Intervention\Application\UseCase\Command\Recurrence\UpdateInterventionRecurrence\{UpdateInterventionRecurrenceCommand, UpdateInterventionRecurrenceResult};
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionRecurrenceInput, UpdateInterventionRecurrenceInput};
use Intervention\Presentation\Api\Dto\Output\InterventionRecurrenceOutput;
use Intervention\Presentation\Api\Factory\InterventionRecurrenceOutputFactory;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\{MergePatchFields, ResourceIriParser};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_key_exists;
use function is_string;

/**
 * Processor InterventionRecurrenceProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, InterventionRecurrenceOutput|null>
 */
final readonly class InterventionRecurrenceProcessor implements ProcessorInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param InterventionRecurrenceOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private InterventionRecurrenceOutputFactory $mapper,
    private Security $security,
    private MergePatchFields $mergePatchFields,
  ) {
  }

  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return ?InterventionRecurrenceOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InterventionRecurrenceOutput
  {
    $user = $this->user();
    $id = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : null;

    try {
      if ($data instanceof CreateInterventionRecurrenceInput) {
        /** @var CreateInterventionRecurrenceResult $result */
        $result = $this->commandBus->dispatch(new CreateInterventionRecurrenceCommand(
          userId: $user->getId(),
          organizationId: ResourceIriParser::id($data->organization, 'organizations'),
          templateId: ResourceIriParser::id($data->template, 'intervention-templates'),
          name: $data->name,
          siteId: null === $data->site ? null : ResourceIriParser::id($data->site, 'facilities'),
          responsibleId: null === $data->responsible ? null : ResourceIriParser::memberId($data->responsible),
          frequency: $data->frequency,
          interval: $data->interval,
          anchorDate: new DateTimeImmutable($data->anchorDate),
          timezone: $data->timezone,
          leadTimeDays: $data->leadTimeDays,
          endAt: null === $data->endAt ? null : new DateTimeImmutable($data->endAt),
        ));

        return $this->mapper->fromView($result->recurrence);
      }

      if ($data instanceof UpdateInterventionRecurrenceInput) {
        if (null === $id) {
          throw new BadRequestHttpException('The id URI parameter is required.');
        }
        $fields = $this->mergePatchFields->all();
        /** @var UpdateInterventionRecurrenceResult $result */
        $result = $this->commandBus->dispatch(new UpdateInterventionRecurrenceCommand(
          userId: $user->getId(),
          recurrenceId: $id,
          name: $data->name,
          siteId: null === $data->site ? null : ResourceIriParser::id($data->site, 'facilities'),
          responsibleId: null === $data->responsible ? null : ResourceIriParser::memberId($data->responsible),
          frequency: $data->frequency,
          interval: $data->interval,
          anchorDate: null === $data->anchorDate ? null : new DateTimeImmutable($data->anchorDate),
          timezone: $data->timezone,
          leadTimeDays: $data->leadTimeDays,
          endAt: null === $data->endAt ? null : new DateTimeImmutable($data->endAt),
          isActive: $data->isActive,
          hasName: array_key_exists('name', $fields),
          hasSiteId: array_key_exists('site', $fields),
          hasResponsibleId: array_key_exists('responsible', $fields),
          hasFrequency: array_key_exists('frequency', $fields),
          hasInterval: array_key_exists('interval', $fields),
          hasAnchorDate: array_key_exists('anchorDate', $fields),
          hasTimezone: array_key_exists('timezone', $fields),
          hasLeadTimeDays: array_key_exists('leadTimeDays', $fields),
          hasEndAt: array_key_exists('endAt', $fields),
          hasIsActive: array_key_exists('isActive', $fields),
        ));

        return $this->mapper->fromView($result->recurrence);
      }

      if (null === $id) {
        throw new BadRequestHttpException('The id URI parameter is required.');
      }
      $this->commandBus->dispatch(new DeleteInterventionRecurrenceCommand($user->getId(), $id));

      return null;
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }
  }

  /**
   * Method user.
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
