<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Mission\Application\Contract\Resource\MissionIssue;
use Mission\Application\UseCase\Query\Workflow\ListMissionIssues\{ListMissionIssuesQuery, ListMissionIssuesResult};
use Mission\Presentation\Api\Dto\Output\MissionIssueOutput;
use Mission\Presentation\Api\Trait\MissionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Throwable;

use function array_map;
use function is_string;
use function sprintf;

/**
 * Provider MissionIssueProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<MissionIssueOutput>
 */
final readonly class MissionIssueProvider implements ProviderInterface
{
  use MissionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the MissionIssueProvider class.
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
   * @return array<int, MissionIssueOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Mission not found.');
    }
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      /** @var ListMissionIssuesResult $result */
      $result = $this->queryBus->ask(new ListMissionIssuesQuery($user->getId(), $id));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }

    return array_map(static function (MissionIssue $issue): MissionIssueOutput {
      $output = new MissionIssueOutput();
      $output->severity = $issue->severity;
      $output->resource = sprintf('/api/%s/%s', 'mission' === $issue->resourceType ? 'missions' : $issue->resourceType, $issue->resourceId);
      $output->field = $issue->field;
      $output->message = $issue->message;

      return $output;
    }, $result->issues);
  }
}
