<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\ChangeOrganizationPlanResult;
use Organization\Application\UseCase\Command\Organization\ChangeSelfServiceOrganizationPlan\ChangeSelfServiceOrganizationPlanCommand;
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationQuery, GetOrganizationResult};
use Organization\Domain\ValueObject\OrganizationSettings;
use Organization\Presentation\Api\Dto\Input\Organization\ChangeOrganizationPlanInput;
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationOutput, OrganizationRegisteredAddressOutput, OrganizationSettingsOutput};
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/**
 * Processor ChangeOrganizationPlanProcessor.
 *
 * Translates HTTP input to the authenticated self-service plan use case.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<ChangeOrganizationPlanInput, OrganizationOutput>
 */
final readonly class ChangeOrganizationPlanProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the authenticated HTTP principal
   */
  public function __construct(private CommandBusPort $commandBus, private QueryBusPort $queryBus, private Security $security)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param ChangeOrganizationPlanInput $data the HTTP plan input
   * @param Operation $operation the API Platform metadata
   * @param array<string, mixed> $uriVariables the resource identifiers
   * @param array<string, mixed> $context the processing context
   *
   * @return OrganizationOutput the refreshed HTTP projection
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationOutput
  {
    /** @var ChangeOrganizationPlanInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $uriVariables['id'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('Organization identifier is required.');
    }
    /** @var ChangeOrganizationPlanResult $changed */
    $changed = $this->commandBus->dispatch(new ChangeSelfServiceOrganizationPlanCommand($organizationId, $data->planId, $user->getId(), $data->acknowledgeOveruse));
    /** @var GetOrganizationResult $result */
    $result = $this->queryBus->ask(new GetOrganizationQuery($changed->organizationId));
    $output = new OrganizationOutput();
    $output->id = $result->id;
    $output->name = $result->name;
    $output->slug = $result->slug;
    $output->ownerUserId = $result->ownerUserId;
    $output->createdByUserId = $result->createdByUserId;
    $output->status = $result->status;
    $output->isActive = $result->isActive;
    $output->description = $result->description;
    $output->logoUrl = $result->logoUrl;
    $output->memberCount = $result->memberCount;
    $output->settings = OrganizationSettingsOutput::fromDomain($result->settings ?? OrganizationSettings::default());
    $output->planId = $result->planId;
    $output->planName = $result->planName;
    $output->country = $result->country;
    $output->legalType = $result->legalType;
    $output->legalName = $result->legalName;
    $output->registrationNumber = $result->registrationNumber;
    $output->vatNumber = $result->vatNumber;
    $output->registeredAddress = OrganizationRegisteredAddressOutput::fromArray($result->registeredAddress);
    $output->privacyContactEmail = $result->privacyContactEmail;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
  // #endregion
}
