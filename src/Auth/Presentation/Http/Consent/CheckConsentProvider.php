<?php

declare(strict_types=1);

namespace Auth\Presentation\Http\Consent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\UseCase\Query\CheckConsent\CheckConsentQuery;
use Auth\Application\UseCase\Query\CheckConsent\CheckConsentResult;
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Presentation\Dto\Output\CheckConsentOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Provider CheckConsentProvider
 * @final
 *
 * Provider for checking consent.
 *
 * @category Provider
 * @package Auth\Presentation\Http\Consent
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<CheckConsentOutput>
 */
final readonly class CheckConsentProvider implements ProviderInterface
{
  public function __construct(
    private Security $security,
    private QueryBusPort $queryBus,
    private RequestStack $requestStack,
  ) {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): CheckConsentOutput
  {
    $user = $this->security->getUser();

    if (!$user instanceof SecurityUser) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'Authentication required',
      );
    }

    $request = $this->requestStack->getCurrentRequest();

    if ($request === null)
      throw new BadRequestHttpException(
        message: 'Request not found',
      );

    $clientId = $request->query->get(key: 'client_id', default: null);
    $scope = $request->query->get(key: 'scope', default: null);

    if (empty($clientId))
      throw new BadRequestHttpException(
        message: 'Missing client_id parameter',
      );

    $requestedScopes = !empty($scope)
      ? explode(' ', $scope)
      : [];

    /** 
     * Query result
     * @var CheckConsentResult $result 
     */
    $result = $this->queryBus->ask(query: new CheckConsentQuery(
      userId: $user->getId(),
      clientId: $clientId,
      requestedScopes: $requestedScopes,
    ));

    return new CheckConsentOutput(
      hasConsent: $result->hasConsent,
      grantedScopes: $result->grantedScopes,
      missingScopes: $result->missingScopes,
      requiresConsentScreen: $result->requiresConsentScreen,
    );
  }
}
