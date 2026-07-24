<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Import\Application\UseCase\Query\GetImportJob\{GetImportJobQuery, GetImportJobResult};
use Import\Presentation\Api\Dto\Output\ImportJobOutput;
use Import\Presentation\Api\Factory\ImportJobOutputFactory;
use Import\Presentation\Api\Trait\ImportExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Provider ImportJobProvider.
 *
 * Handles `GET /imports/{id}`.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<ImportJobOutput>
 */
final readonly class ImportJobProvider implements ProviderInterface
{
  use ImportExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param ImportJobOutputFactory $outputFactory the output factory value
   * @param Security $security the security value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private ImportJobOutputFactory $outputFactory,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return ImportJobOutput the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ImportJobOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $id = $uriVariables['id'] ?? null;
    if (!is_string($id) || '' === $id) {
      throw new BadRequestHttpException('The id URI parameter is required.');
    }

    try {
      /** @var GetImportJobResult $result */
      $result = $this->queryBus->ask(new GetImportJobQuery($user->getId(), $id));
    } catch (Throwable $exception) {
      throw $this->mapImportException($exception);
    }

    return $this->outputFactory->fromView($result);
  }
  // #endregion
}
