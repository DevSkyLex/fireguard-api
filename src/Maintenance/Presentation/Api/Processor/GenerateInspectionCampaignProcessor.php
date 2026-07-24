<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Exception;
use Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign\{GenerateInspectionCampaignCommand, GenerateInspectionCampaignResult};
use Maintenance\Presentation\Api\Dto\Input\GenerateInspectionCampaignInput;
use Maintenance\Presentation\Api\Dto\Output\GenerateInspectionCampaignOutput;
use Maintenance\Presentation\Api\Trait\MaintenanceExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

/**
 * Processor GenerateInspectionCampaignProcessor.
 *
 * Handles `POST /api/maintenance/campaigns`.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<GenerateInspectionCampaignInput, GenerateInspectionCampaignOutput>
 */
final readonly class GenerateInspectionCampaignProcessor implements ProcessorInterface
{
  use MaintenanceExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param Security $security the security value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
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
   * @return GenerateInspectionCampaignOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): GenerateInspectionCampaignOutput
  {
    $user = $this->user();

    if (!$data instanceof GenerateInspectionCampaignInput) {
      throw new BadRequestHttpException('Invalid request body.');
    }

    try {
      $dueBefore = new DateTimeImmutable($data->dueBefore);
    } catch (Exception $exception) {
      throw new BadRequestHttpException('Invalid "dueBefore" value.', $exception);
    }

    try {
      /** @var GenerateInspectionCampaignResult $result */
      $result = $this->commandBus->dispatch(new GenerateInspectionCampaignCommand(
        organizationId: ResourceIriParser::id($data->organization, 'organizations'),
        actorUserId: $user->getId(),
        name: $data->name,
        facilityId: null === $data->facility ? null : ResourceIriParser::id($data->facility, 'facilities'),
        equipmentType: $data->equipmentType,
        dueBefore: $dueBefore,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMaintenanceException($exception);
    }

    $output = new GenerateInspectionCampaignOutput();
    $output->interventionId = $result->interventionId;
    $output->number = $result->number;
    $output->workItemsCount = $result->workItemsCount;

    return $output;
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
