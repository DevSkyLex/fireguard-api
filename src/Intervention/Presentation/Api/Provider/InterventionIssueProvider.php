<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Resource\InterventionIssue;
use Intervention\Application\UseCase\Query\Workflow\ListInterventionIssues\{ListInterventionIssuesQuery, ListInterventionIssuesResult};
use Intervention\Presentation\Api\Dto\Output\InterventionIssueOutput;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Throwable;

use function array_map;
use function is_string;
use function sprintf;

/**
 * Provider InterventionIssueProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<InterventionIssueOutput>
 */
final readonly class InterventionIssueProvider implements ProviderInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionIssueProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param Security $security the security value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
  ) {
  }

  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return array<int, InterventionIssueOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Intervention not found.');
    }
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      /** @var ListInterventionIssuesResult $result */
      $result = $this->queryBus->ask(new ListInterventionIssuesQuery($user->getId(), $id));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }

    return array_map(static function (InterventionIssue $issue): InterventionIssueOutput {
      $output = new InterventionIssueOutput();
      $output->severity = $issue->severity;
      $output->resource = sprintf('/api/%s/%s', 'intervention' === $issue->resourceType ? 'interventions' : $issue->resourceType, $issue->resourceId);
      $output->field = $issue->field;
      $output->message = $issue->message;

      return $output;
    }, $result->issues);
  }
}
