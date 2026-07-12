<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Activity\AddInterventionComment;

use Intervention\Application\Port\Outbound\{InterventionActivityPort, InterventionWorkflowGatewayPort};
use Intervention\Application\Service\InterventionMemberPolicy;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException, InterventionValidationException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;

use function trim;

/**
 * UseCase AddInterventionCommentHandler.
 *
 * Adds a member comment to an intervention's activity feed. The comment is
 * attributed to the authenticated user's organization member id, resolved
 * and asserted active through {@see InterventionMemberPolicy}, so the read
 * side can always render the actor as a member IRI.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddInterventionCommentHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the AddInterventionCommentHandler class.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowGatewayPort $gateway the gateway value
   * @param InterventionActivityPort $activities the activities value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param InterventionMemberPolicy $memberPolicy the intervention member policy
   */
  public function __construct(
    private InterventionWorkflowGatewayPort $gateway,
    private InterventionActivityPort $activities,
    private OrganizationAuthorizationPort $authorization,
    private InterventionMemberPolicy $memberPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * Executes the   invoke operation.
   *
   * @since 1.0.0
   *
   * @param AddInterventionCommentCommand $command the command value
   *
   * @return AddInterventionCommentResult the   invoke result
   */
  public function __invoke(AddInterventionCommentCommand $command): AddInterventionCommentResult
  {
    $context = $this->gateway->interventionContext($command->interventionId);
    if (null === $context) {
      throw InterventionNotFoundException::withId($command->interventionId);
    }
    if (!$this->authorization->hasPermission($command->userId, $context->organizationId, 'organization.interventions.read')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    $body = trim($command->body);
    if ('' === $body) {
      throw new InterventionValidationException('The comment body must not be blank.');
    }

    $actorId = $this->memberPolicy->assertActiveMemberForUser($context->organizationId, $command->userId);

    $view = $this->activities->append(
      $command->interventionId,
      $context->organizationId,
      $actorId,
      'comment',
      'comment',
      $body,
      null,
    );

    return new AddInterventionCommentResult($view);
  }
}
