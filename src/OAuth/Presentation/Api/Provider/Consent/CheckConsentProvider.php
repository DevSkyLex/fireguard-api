<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Provider\Consent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use OAuth\Application\UseCase\Query\CheckConsent\CheckConsentQuery;
use OAuth\Application\UseCase\Query\CheckConsent\CheckConsentResult;
use Auth\Infrastructure\Security\User\SecurityUser;
use OAuth\Presentation\Api\Dto\Output\CheckConsentOutput;
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
 * @package OAuth\Presentation\Api\Provider\Consent
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<CheckConsentOutput>
 */
final readonly class CheckConsentProvider implements ProviderInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * CheckConsentProvider class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Security $security The security service.
   * @param QueryBusPort $queryBus The query bus.
   * @param RequestStack $requestStack The request stack.
   */
  public function __construct(
    private readonly Security $security,
    private readonly QueryBusPort $queryBus,
    private readonly RequestStack $requestStack,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Provides the consent check result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return CheckConsentOutput The consent check result.
   */
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
