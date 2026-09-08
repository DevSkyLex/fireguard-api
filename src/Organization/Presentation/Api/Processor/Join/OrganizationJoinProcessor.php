<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Join;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use LogicException;
use Organization\Application\UseCase\Command\Join\ManageOrganizationJoin\{ManageOrganizationJoinCommand, ManageOrganizationJoinResult};
use Organization\Presentation\Api\Dto\Input\Join\{ApproveOrganizationJoinRequestInput, OrganizationAccessPolicyInput, OrganizationDomainInput};
use Organization\Presentation\Api\Service\Join\OrganizationJoinOutputAssembler;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function is_string;

/**
 * Processor OrganizationJoinProcessor.
 *
 * @implements ProcessorInterface<mixed,object>
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationJoinProcessor implements ProcessorInterface
{
  /**
   * @since 1.0.0
   *
   * @param CommandBusPort $commands bus
   * @param Security $security actor
   * @param OrganizationJoinOutputAssembler $assembler output projection
   * @param RateLimiterFactory $limiter mutation budget
   * @param RateLimiterFactory $domainLimiter DNS budget
   */
  public function __construct(private CommandBusPort $commands, private Security $security, private OrganizationJoinOutputAssembler $assembler, #[Autowire(service: 'limiter.organization_join')] private RateLimiterFactory $limiter, #[Autowire(service: 'limiter.organization_domain_verify')] private RateLimiterFactory $domainLimiter)
  {
  }

  /**
   * @since 1.0.0
   *
   * @param mixed $data validated DTO
   * @param Operation $operation metadata
   * @param array<string,mixed> $uriVariables scoped variables
   * @param array<string,mixed> $context serialization context
   *
   * @return object output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $actor = $this->security->getUser();
    if (!$actor instanceof SecurityUser) {
      throw new AccessDeniedHttpException();
    }
    $action = $operation->getExtraProperties()['join_action'] ?? null;
    if (!is_string($action)) {
      throw new LogicException('Missing join action.');
    }
    if (!$this->limiter->create($actor->getId())->consume()->isAccepted()) {
      throw new TooManyRequestsHttpException();
    }
    if ('domain_verify' === $action && !$this->domainLimiter->create($actor->getId())->consume()->isAccepted()) {
      throw new TooManyRequestsHttpException();
    }
    $organizationId = $uriVariables['organizationId'] ?? null;
    $resourceId = $uriVariables['domainId'] ?? $uriVariables['requestId'] ?? $uriVariables['invitationId'] ?? null;
    $result = $this->commands->dispatch(new ManageOrganizationJoinCommand(
      $action,
      $actor->getId(),
      is_string($organizationId) ? $organizationId : null,
      is_string($resourceId) ? $resourceId : null,
      $data instanceof OrganizationAccessPolicyInput ? $data->mode : null,
      $data instanceof OrganizationAccessPolicyInput ? $data->roleId : null,
      $data instanceof OrganizationDomainInput ? $data->domain : null,
      $data instanceof ApproveOrganizationJoinRequestInput ? $data->roleIds : [],
    ));
    if (!$result instanceof ManageOrganizationJoinResult) {
      throw new LogicException('Unexpected join command result.');
    }

    return $this->assembler->assemble($operation, $result->data);
  }
}
