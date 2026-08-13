<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Activity\AddInterventionComment;

use Intervention\Application\Port\Outbound\{InterventionActivityPort, InterventionWorkflowGatewayPort};
use Intervention\Application\Service\{InterventionMemberPolicy, InterventionNotificationService};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException, InterventionValidationException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;

use function array_unique;
use function array_values;
use function preg_match_all;
use function trim;

/**
 * UseCase AddInterventionCommentHandler.
 *
 * Adds a member comment to an intervention's activity feed. The comment is
 * attributed to the authenticated user's organization member id, resolved
 * and asserted active through {@see InterventionMemberPolicy}, so the read
 * side can always render the actor as a member IRI.
 *
 * Mention tokens of the form `@{memberUuid}` inside the body notify the
 * mentioned members (best-effort, after the comment is persisted); membership
 * and activity checks happen in the notification service since the ids come
 * from user input.
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
   * @param InterventionNotificationService $notifications the intervention notification service
   */
  public function __construct(
    private InterventionWorkflowGatewayPort $gateway,
    private InterventionActivityPort $activities,
    private OrganizationAuthorizationPort $authorization,
    private InterventionMemberPolicy $memberPolicy,
    private InterventionNotificationService $notifications,
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
    $decision = $this->authorization->resolveAccess($command->userId, $context->organizationId, 'organization.interventions.read');
    if ($decision->isOutsideScope()) {
      throw InterventionNotFoundException::withId($command->interventionId);
    }
    if (!$decision->isGranted()) {
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
      $command->clientId,
    );

    foreach (self::extractMentions($body) as $mentionedMemberId) {
      if ($mentionedMemberId === $actorId) {
        continue;
      }

      $this->notifications->mentioned($command->interventionId, $context->organizationId, $mentionedMemberId);
    }

    return new AddInterventionCommentResult($view);
  }

  /**
   * Method extractMentions.
   *
   * @static
   *
   * Extracts the unique member identifiers mentioned in a comment body using
   * the `@{memberUuid}` token form (which survives rich-text sanitization).
   *
   * @since 1.1.0
   *
   * @param string $body the comment body
   *
   * @return list<string> the mentioned member identifiers
   */
  private static function extractMentions(string $body): array
  {
    if (1 > preg_match_all('/(?:@|&#64;)\{([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})\}/', $body, $matches)) {
      return [];
    }

    return array_values(array_unique($matches[1]));
  }
}
