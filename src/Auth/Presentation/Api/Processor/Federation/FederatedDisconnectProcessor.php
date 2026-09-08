<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Federation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Service\Federation\FederatedAuthenticationService;
use Auth\Domain\Exception\Federation\{FederatedAuthException, FederatedConflictException, FederatedUnauthorizedException};
use Auth\Domain\ValueObject\Federation\FederatedProvider;
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Presentation\Api\Dto\Output\Federation\FederatedConnectionsOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, UnauthorizedHttpException};

use function is_string;

/**
 * @implements ProcessorInterface<void, FederatedConnectionsOutput>
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedDisconnectProcessor implements ProcessorInterface
{
  public function __construct(
    private FederatedAuthenticationService $federation,
    private Security $security,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FederatedConnectionsOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $value = $uriVariables['provider'] ?? null;
    $provider = is_string($value) ? FederatedProvider::tryFrom($value) : null;
    if (null === $provider) {
      throw new BadRequestHttpException('unknown_provider');
    }

    try {
      return FederatedConnectionsOutput::fromContract($this->federation->remove($provider, $user->getId()));
    } catch (FederatedConflictException $exception) {
      throw new ConflictHttpException($exception->errorCode, $exception);
    } catch (FederatedUnauthorizedException $exception) {
      throw new UnauthorizedHttpException('Bearer', $exception->errorCode, $exception);
    } catch (FederatedAuthException $exception) {
      throw new BadRequestHttpException($exception->errorCode, $exception);
    }
  }
}
