<?php

declare(strict_types=1);

namespace Auth\Infrastructure\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function array_filter;
use function array_map;
use function in_array;
use function is_array;

/**
 * Class OAuthRequestScopeSubscriber
 *
 * Intersects a verified OAuth delegation with the endpoint's existing RBAC and organization checks.
 * Runs after authentication and before API Platform providers or deserialization.
 *
 * @category EventSubscriber
 */
final readonly class OAuthRequestScopeSubscriber implements EventSubscriberInterface
{
  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * @access public
   *
   * @return array<string, array{string, int}> the request hook after the security firewall
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::REQUEST => ['onKernelRequest', 6]];
  }

  /**
   * Method onKernelRequest
   *
   * Auth-session tokens carry no OAuth-delegation marker and retain their existing authorization.
   *
   * @access public
   *
   * @param RequestEvent $event the authenticated request
   *
   * @return void
   */
  public function onKernelRequest(RequestEvent $event): void
  {
    $request = $event->getRequest();
    if (!$request->attributes->has('_fireguard_oauth_scopes')) {
      return;
    }
    $scopes = $request->attributes->get('_fireguard_oauth_scopes');
    $requiredScope = $this->requiredScope($request);
    if (null === $requiredScope) {
      return;
    }
    if (is_array($scopes) && in_array($requiredScope, array_map('strtoupper', array_filter($scopes, 'is_string')), true)) {
      return;
    }

    $event->setResponse(new JsonResponse(
      ['error' => 'insufficient_scope', 'error_description' => 'The access token does not authorize this operation.'],
      Response::HTTP_FORBIDDEN,
      ['WWW-Authenticate' => 'Bearer error="insufficient_scope", scope="' . $requiredScope . '"'],
    ));
  }

  /**
   * Method requiredScope
   *
   * Keeps identity-only protocol operations separate from consent and account management.
   * Scope families are independent: ADMIN and WRITE do not imply READ or DELETE.
   *
   * @access private
   *
   * @param Request $request the routed request
   *
   * @return string|null the delegated capability, or null for an identity protocol operation
   */
  private function requiredScope(Request $request): ?string
  {
    $method = $request->getMethod();
    $operation = $request->attributes->get('_api_operation_name');
    $resource = $request->attributes->get('_api_resource_class');
    if ($this->isIdentityProtocolOperation($resource, $operation, $method)) {
      return null;
    }
    if ($this->requiresAdministration($resource, $operation)) {
      return 'ADMIN';
    }

    return 'POST' === $method && 'workload_assess' === $operation
      ? 'READ'
      : match ($method) {
        'GET', 'HEAD', 'OPTIONS' => 'READ',
        'POST', 'PUT', 'PATCH' => 'WRITE',
        'DELETE' => 'DELETE',
        default => 'ADMIN',
      };
  }

  /**
   * Method isIdentityProtocolOperation
   *
   * Recognizes only the routed OAuth identity operations exempt from business delegation scopes.
   *
   * @access private
   *
   * @param mixed $resource the canonical routed API resource
   * @param mixed $operation the canonical routed API operation
   * @param string $method the HTTP request method
   *
   * @return bool whether the operation retains its own protocol authorization
   */
  private function isIdentityProtocolOperation(mixed $resource, mixed $operation, string $method): bool
  {
    return 'OAuth\\Presentation\\Api\\Resource\\OAuth2Resource' === $resource
      && (('GET' === $method && 'userinfo' === $operation)
        || ('POST' === $method && in_array($operation, ['revoke_token', 'introspect_token'], true)));
  }

  /**
   * Method requiresAdministration
   *
   * Keeps routed account administration and OAuth grant management behind the independent ADMIN capability.
   *
   * @access private
   *
   * @param mixed $resource the canonical routed API resource
   * @param mixed $operation the canonical routed API operation
   *
   * @return bool whether the delegated operation requires ADMIN
   */
  private function requiresAdministration(mixed $resource, mixed $operation): bool
  {
    return ('OAuth\\Presentation\\Api\\Resource\\OAuth2Resource' === $resource
      && in_array($operation, ['authorize', 'check_consent', 'grant_consent'], true))
      || in_array($resource, [
        'OAuth\\Presentation\\Api\\Resource\\ClientResource',
        'Tenant\\Presentation\\Api\\Resource\\TenantResource',
        'Authorization\\Presentation\\Api\\Resource\\RoleResource',
        'Authorization\\Presentation\\Api\\Resource\\PermissionResource',
        'User\\Presentation\\Api\\Resource\\UserResource',
        'Organization\\Presentation\\Api\\Resource\\OrganizationRoleResource',
      ], true);
  }
  // #endregion
}
