<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\EventSubscriber;

use Auth\Infrastructure\EventSubscriber\OAuthRequestScopeSubscriber;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Class OAuthRequestScopeSubscriberTest
 *
 * Verifies independent delegation capabilities across providers, writes and controllers.
 *
 * @category Unit Test
 */
#[CoversClass(OAuthRequestScopeSubscriber::class)]
final class OAuthRequestScopeSubscriberTest extends TestCase
{
  // #region Methods
  /**
   * Method testDelegationCapabilities
   *
   * @access public
   *
   * @param list<string>|null $scopes validated OAuth scopes or an interactive session
   * @param string $method request method
   * @param string $path routed API path
   * @param bool $allowed whether the scope gate permits existing RBAC to proceed
   * @param string|null $operation the canonical API operation
   * @param string|null $resource the canonical API resource
   *
   * @return void
   */
  #[Test]
  #[DataProvider('delegations')]
  public function testDelegationCapabilities(?array $scopes, string $method, string $path, bool $allowed, ?string $operation = null, ?string $resource = null): void
  {
    $request = Request::create($path, $method);
    if (null !== $scopes) {
      $request->attributes->set('_fireguard_oauth_scopes', $scopes);
    }
    $request->attributes->set('_api_operation_name', $operation);
    $request->attributes->set('_api_resource_class', $resource);
    $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

    new OAuthRequestScopeSubscriber()->onKernelRequest($event);

    self::assertSame(!$allowed, $event->hasResponse());
    if (!$allowed) {
      $response = $event->getResponse();
      self::assertNotNull($response);
      self::assertSame(403, $response->getStatusCode());
      self::assertStringContainsString('insufficient_scope', $response->getContent() ?: '');
    }
  }

  /**
   * Method delegations
   *
   * @access public
   *
   * @return array<string, array{list<string>|null, string, string, bool, string|null, string|null}>
   */
  public static function delegations(): array
  {
    $clients = 'OAuth\\Presentation\\Api\\Resource\\ClientResource';
    $roles = 'Organization\\Presentation\\Api\\Resource\\OrganizationRoleResource';
    $protocol = 'OAuth\\Presentation\\Api\\Resource\\OAuth2Resource';

    return [
      'OIDC cannot read business' => [['OPENID', 'PROFILE', 'EMAIL'], 'GET', '/api/organizations', false, null, null],
      'empty cannot read business' => [[], 'GET', '/api/organizations', false, null, null],
      'READ permits export controller' => [['READ'], 'GET', '/api/audit-events/export', true, null, null],
      'READ cannot mutate' => [['READ'], 'PATCH', '/api/me', false, null, null],
      'WRITE permits mutation' => [['WRITE'], 'PATCH', '/api/me', true, null, null],
      'WRITE cannot delete' => [['WRITE'], 'DELETE', '/api/organizations/example', false, null, null],
      'DELETE permits deletion' => [['DELETE'], 'DELETE', '/api/organizations/example', true, null, null],
      'ADMIN does not imply READ' => [['ADMIN'], 'GET', '/api/organizations', false, null, null],
      'READ cannot administer clients' => [['READ'], 'GET', '/api/clients', false, 'oauth_client_list', $clients],
      'ADMIN permits client RBAC check' => [['ADMIN'], 'GET', '/api/clients', true, 'oauth_client_list', $clients],
      'ADMIN needed for roles' => [['WRITE'], 'POST', '/api/organizations/example/roles', false, 'createOrganizationRole', $roles],
      'encoded path retains administrative scope' => [['READ'], 'GET', '/api/%63lients', false, 'oauth_client_list', $clients],
      'format suffix retains administrative scope' => [['READ'], 'GET', '/api/clients.json', false, 'oauth_client_list', $clients],
      'READ permits read POST' => [['READ'], 'POST', '/api/organizations/example/workload/assessments', true, 'workload_assess', null],
      'READ cannot use unknown POST' => [['READ'], 'POST', '/api/organizations', false, 'organization_create', null],
      'OIDC UserInfo remains permitted' => [['OPENID'], 'GET', '/api/oauth2/userinfo', true, 'userinfo', $protocol],
      'OIDC introspection remains permitted' => [['OPENID'], 'POST', '/api/oauth2/token/introspect', true, 'introspect_token', $protocol],
      'OIDC revocation remains permitted' => [['OPENID'], 'POST', '/api/oauth2/token/revoke', true, 'revoke_token', $protocol],
      'UserInfo path cannot exempt a different operation' => [['OPENID'], 'GET', '/api/oauth2/userinfo', false, 'organization_get', null],
      'OIDC cannot authorize broader grants' => [['OPENID'], 'GET', '/api/oauth2/authorize', false, 'authorize', $protocol],
      'interactive WRITE remains permitted' => [null, 'POST', '/api/organizations', true, null, null],
    ];
  }
  // #endregion
}
