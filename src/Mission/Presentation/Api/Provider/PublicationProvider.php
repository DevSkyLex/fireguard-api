<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Mission\Application\UseCase\Query\Publication\GetPublication\{GetPublicationQuery, GetPublicationResult};
use Mission\Domain\Exception\{MissionAccessDeniedException, PublicationNotFoundException};
use Mission\Presentation\Api\Dto\Output\PublicationOutput;
use Mission\Presentation\Api\Factory\PublicationOutputFactory;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Throwable;

use function is_string;

/**
 * Provider PublicationProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<PublicationOutput>
 */
final readonly class PublicationProvider implements ProviderInterface
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the PublicationProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param PublicationOutputFactory $outputMapper the output mapper value
   * @param Security $security the security value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private PublicationOutputFactory $outputMapper,
    private Security $security,
  ) {
  }

  /**
   * Method provide.
   *
   * Executes the provide operation.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return PublicationOutput the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicationOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Publication not found.');
    }
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      /** @var GetPublicationResult $result */
      $result = $this->queryBus->ask(new GetPublicationQuery($user->getId(), $id));
    } catch (MessengerRuntimeException $exception) {
      throw $this->mapException($exception);
    } catch (MissionAccessDeniedException|PublicationNotFoundException $exception) {
      throw $this->mapException($exception);
    }

    return $this->outputMapper->fromView($result->publication);
  }

  /**
   * Method mapException.
   *
   * Executes the map exception operation.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the map exception result
   */
  private function mapException(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      $mapped = match (true) {
        $current instanceof MissionAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof PublicationNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        default => null,
      };
      if ($mapped instanceof Throwable) {
        return $mapped;
      }
      $current = $current->getPrevious();
    } while ($current instanceof Throwable);

    return $exception;
  }
}
