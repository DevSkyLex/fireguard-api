<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Federation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Service\Federation\FederatedAuthenticationService;
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Auth\Domain\ValueObject\Federation\FederatedProvider;
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Presentation\Api\Dto\Input\Federation\FederatedStartInput;
use Auth\Presentation\Api\Dto\Output\Federation\FederatedStartOutput;
use Auth\Presentation\Api\Operation\FederatedAuthOperations;
use Auth\Presentation\Api\Service\FederatedFlowCookieService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function base64_encode;
use function hash;
use function is_string;
use function max;
use function random_bytes;
use function rtrim;
use function sprintf;
use function strtr;
use function substr;
use function time;

/**
 * @implements ProcessorInterface<FederatedStartInput, FederatedStartOutput>
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedStartProcessor implements ProcessorInterface
{
  public function __construct(
    private FederatedAuthenticationService $federation,
    private Security $security,
    private RequestStack $requestStack,
    private FederatedFlowCookieService $flowCookieService,
    #[Autowire(service: 'limiter.federated_start')]
    private RateLimiterFactory $rateLimiter,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FederatedStartOutput
  {
    $provider = $this->provider($uriVariables);
    $request = $this->requestStack->getCurrentRequest();
    $ipAddress = $request?->getClientIp() ?? '127.0.0.1';
    $this->enforceRateLimit($ipAddress);
    $browserBinding = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

    try {
      if (FederatedAuthOperations::LINK_START === $operation->getName()) {
        $user = $this->security->getUser();
        if (!$user instanceof SecurityUser) {
          throw new AccessDeniedHttpException('Authentication required.');
        }
        $url = $this->federation->startLink($provider, $user->getId(), $browserBinding);
      } else {
        $url = $this->federation->startLogin($provider, $data->returnUrl, $browserBinding);
      }
    } catch (FederatedAuthException $exception) {
      throw new BadRequestHttpException($exception->errorCode, $exception);
    }

    $request?->attributes->set('_federated_flow_cookie', $this->flowCookieService->createCookie($browserBinding));

    return new FederatedStartOutput($url);
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  private function provider(array $uriVariables): FederatedProvider
  {
    $value = $uriVariables['provider'] ?? null;
    $provider = is_string($value) ? FederatedProvider::tryFrom($value) : null;
    if (null === $provider) {
      throw new BadRequestHttpException('unknown_provider');
    }

    return $provider;
  }

  private function enforceRateLimit(string $ipAddress): void
  {
    $limit = $this->rateLimiter->create(substr(hash('sha256', $ipAddress), 0, 24))->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $seconds = max(0, $limit->getRetryAfter()->getTimestamp() - time());

    throw new TooManyRequestsHttpException($seconds, sprintf('Try again in %d seconds.', $seconds));
  }
}
