<?php

declare(strict_types=1);

namespace Tests\Unit\Smoke;

use DateTimeImmutable;
use DateTimeInterface;
use OAuth\Domain\ValueObject\Client\RedirectUri;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Domain\ValueObject\Security\{GrantType, GrantTypes};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Shared\Domain\ValueObject\Uuid;
use Tenant\Domain\ValueObject\TenantSettings;

use function class_exists;
use function enum_exists;
use function is_a;
use function is_subclass_of;
use function property_exists;
use function str_contains;

/**
 * Test UseCaseMessageConstructionTest.
 *
 * @category Smoke Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UseCaseMessageConstructionTest extends TestCase
{
  // #region Tests
  #[Test]
  #[DataProvider('messageClassProvider')]
  public function testConstruction(string $className): void
  {
    self::assertTrue(class_exists($className), "Class {$className} does not exist");

    /** @phpstan-var class-string $className */
    $reflection = new ReflectionClass($className);
    $args = $this->buildArgs($reflection, 0);

    $instance = $reflection->newInstanceArgs($args);

    self::assertInstanceOf($className, $instance);

    $constructor = $reflection->getConstructor();
    if (null === $constructor) {
      return;
    }

    $params = $constructor->getParameters();
    foreach ($params as $index => $param) {
      $property = $param->getName();
      if (!property_exists($instance, $property)) {
        continue;
      }

      if ($reflection->hasProperty($property) && !$reflection->getProperty($property)->isPublic()) {
        continue;
      }

      $expected = $args[$index] ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null);

      self::assertSame($expected, $instance->{$property});
    }
  }

  /**
   * @return iterable<string, array{0: class-string}>
   */
  public static function messageClassProvider(): iterable
  {
    yield 'Auth.LoginCommand' => [\Auth\Application\UseCase\Command\Session\Login\LoginCommand::class];
    yield 'Auth.LoginResult' => [\Auth\Application\UseCase\Command\Session\Login\LoginResult::class];
    yield 'Auth.LogoutCommand' => [\Auth\Application\UseCase\Command\Session\Logout\LogoutCommand::class];
    yield 'Auth.LogoutResult' => [\Auth\Application\UseCase\Command\Session\Logout\LogoutResult::class];
    yield 'Auth.RefreshTokenQuery' => [\Auth\Application\UseCase\Query\Session\RefreshToken\RefreshTokenQuery::class];
    yield 'Auth.RefreshTokenResult' => [\Auth\Application\UseCase\Query\Session\RefreshToken\RefreshTokenResult::class];
    yield 'Auth.MfaChallengeCommand' => [\Auth\Application\UseCase\Command\Mfa\MfaChallenge\MfaChallengeCommand::class];
    yield 'Auth.MfaChallengeResult' => [\Auth\Application\UseCase\Command\Mfa\MfaChallenge\MfaChallengeResult::class];
    yield 'Auth.MfaVerifyCommand' => [\Auth\Application\UseCase\Command\Mfa\MfaVerify\MfaVerifyCommand::class];
    yield 'Auth.MfaVerifyResult' => [\Auth\Application\UseCase\Command\Mfa\MfaVerify\MfaVerifyResult::class];
    yield 'Auth.UserAuthenticationResult' => [\Auth\Application\Contract\User\UserAuthenticationResult::class];
    yield 'Auth.AccessTokenStatus' => [\Auth\Application\Contract\Token\AccessTokenStatus::class];

    yield 'User.CreateUserCommand' => [\User\Application\UseCase\Command\User\CreateUser\CreateUserCommand::class];
    yield 'User.CreateUserResult' => [\User\Application\UseCase\Command\User\CreateUser\CreateUserResult::class];
    yield 'User.UpdateUserCommand' => [\User\Application\UseCase\Command\User\UpdateUser\UpdateUserCommand::class];
    yield 'User.UpdateUserResult' => [\User\Application\UseCase\Command\User\UpdateUser\UpdateUserResult::class];
    yield 'User.DeleteUserCommand' => [\User\Application\UseCase\Command\User\DeleteUser\DeleteUserCommand::class];
    yield 'User.DeleteUserResult' => [\User\Application\UseCase\Command\User\DeleteUser\DeleteUserResult::class];
    yield 'User.AuthenticateUserQuery' => [\User\Application\UseCase\Query\User\AuthenticateUser\AuthenticateUserQuery::class];
    yield 'User.AuthenticateUserResult' => [\User\Application\UseCase\Query\User\AuthenticateUser\AuthenticateUserResult::class];
    yield 'User.GetUserQuery' => [\User\Application\UseCase\Query\User\GetUser\GetUserQuery::class];
    yield 'User.GetUserResult' => [\User\Application\UseCase\Query\User\GetUser\GetUserResult::class];
    yield 'User.ListUsersQuery' => [\User\Application\UseCase\Query\User\ListUsers\ListUsersQuery::class];
    yield 'User.UserView' => [\User\Application\Contract\User\UserView::class];

    yield 'Tenant.CreateTenantCommand' => [\Tenant\Application\UseCase\Command\Tenant\CreateTenant\CreateTenantCommand::class];
    yield 'Tenant.CreateTenantResult' => [\Tenant\Application\UseCase\Command\Tenant\CreateTenant\CreateTenantResult::class];
    yield 'Tenant.UpdateTenantCommand' => [\Tenant\Application\UseCase\Command\Tenant\UpdateTenant\UpdateTenantCommand::class];
    yield 'Tenant.UpdateTenantResult' => [\Tenant\Application\UseCase\Command\Tenant\UpdateTenant\UpdateTenantResult::class];
    yield 'Tenant.DeleteTenantCommand' => [\Tenant\Application\UseCase\Command\Tenant\DeleteTenant\DeleteTenantCommand::class];
    yield 'Tenant.DeleteTenantResult' => [\Tenant\Application\UseCase\Command\Tenant\DeleteTenant\DeleteTenantResult::class];
    yield 'Tenant.ActivateTenantCommand' => [\Tenant\Application\UseCase\Command\Tenant\ActivateTenant\ActivateTenantCommand::class];
    yield 'Tenant.DeactivateTenantCommand' => [\Tenant\Application\UseCase\Command\Tenant\DeactivateTenant\DeactivateTenantCommand::class];
    yield 'Tenant.GetTenantQuery' => [\Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantQuery::class];
    yield 'Tenant.GetTenantResult' => [\Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantResult::class];
    yield 'Tenant.ListTenantsQuery' => [\Tenant\Application\UseCase\Query\Tenant\ListTenants\ListTenantsQuery::class];
    yield 'Tenant.ListTenantsResult' => [\Tenant\Application\UseCase\Query\Tenant\ListTenants\ListTenantsResult::class];

    yield 'Session.CreateSessionCommand' => [\Session\Application\UseCase\Command\Session\CreateSession\CreateSessionCommand::class];
    yield 'Session.CreateSessionResult' => [\Session\Application\UseCase\Command\Session\CreateSession\CreateSessionResult::class];
    yield 'Session.RevokeSessionCommand' => [\Session\Application\UseCase\Command\Session\RevokeSession\RevokeSessionCommand::class];
    yield 'Session.RevokeSessionResult' => [\Session\Application\UseCase\Command\Session\RevokeSession\RevokeSessionResult::class];
    yield 'Session.RevokeSessionByTokenCommand' => [\Session\Application\UseCase\Command\Session\RevokeSessionByToken\RevokeSessionByTokenCommand::class];
    yield 'Session.RevokeSessionByTokenResult' => [\Session\Application\UseCase\Command\Session\RevokeSessionByToken\RevokeSessionByTokenResult::class];
    yield 'Session.RevokeAllUserSessionsCommand' => [\Session\Application\UseCase\Command\Session\RevokeAllUserSessions\RevokeAllUserSessionsCommand::class];
    yield 'Session.RevokeAllUserSessionsResult' => [\Session\Application\UseCase\Command\Session\RevokeAllUserSessions\RevokeAllUserSessionsResult::class];
    yield 'Session.UpdateSessionTokensCommand' => [\Session\Application\UseCase\Command\Session\UpdateSessionTokens\UpdateSessionTokensCommand::class];
    yield 'Session.UpdateSessionTokensResult' => [\Session\Application\UseCase\Command\Session\UpdateSessionTokens\UpdateSessionTokensResult::class];
    yield 'Session.GetSessionQuery' => [\Session\Application\UseCase\Query\Session\GetSession\GetSessionQuery::class];
    yield 'Session.GetSessionResult' => [\Session\Application\UseCase\Query\Session\GetSession\GetSessionResult::class];
    yield 'Session.ListUserSessionsQuery' => [\Session\Application\UseCase\Query\Session\ListUserSessions\ListUserSessionsQuery::class];
    yield 'Session.ListUserSessionsResult' => [\Session\Application\UseCase\Query\Session\ListUserSessions\ListUserSessionsResult::class];

    yield 'TrustedDevice.TrustDeviceCommand' => [\TrustedDevice\Application\UseCase\Command\TrustedDevice\TrustDevice\TrustDeviceCommand::class];
    yield 'TrustedDevice.TrustDeviceResult' => [\TrustedDevice\Application\UseCase\Command\TrustedDevice\TrustDevice\TrustDeviceResult::class];
    yield 'TrustedDevice.RevokeDeviceCommand' => [\TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeDevice\RevokeDeviceCommand::class];
    yield 'TrustedDevice.RevokeDeviceResult' => [\TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeDevice\RevokeDeviceResult::class];
    yield 'TrustedDevice.RevokeAllDevicesCommand' => [\TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeAllDevices\RevokeAllDevicesCommand::class];
    yield 'TrustedDevice.RevokeAllDevicesResult' => [\TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeAllDevices\RevokeAllDevicesResult::class];
    yield 'TrustedDevice.CheckDeviceTrustedQuery' => [\TrustedDevice\Application\UseCase\Query\TrustedDevice\CheckDeviceTrusted\CheckDeviceTrustedQuery::class];
    yield 'TrustedDevice.CheckDeviceTrustedResult' => [\TrustedDevice\Application\UseCase\Query\TrustedDevice\CheckDeviceTrusted\CheckDeviceTrustedResult::class];
    yield 'TrustedDevice.ListTrustedDevicesQuery' => [\TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices\ListTrustedDevicesQuery::class];
    yield 'TrustedDevice.ListTrustedDevicesResult' => [\TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices\ListTrustedDevicesResult::class];
    yield 'TrustedDevice.TrustedDeviceItemResult' => [\TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices\TrustedDeviceItemResult::class];

    yield 'Otp.GenerateOtpCommand' => [\Otp\Application\UseCase\Command\Challenge\GenerateOtp\GenerateOtpCommand::class];
    yield 'Otp.GenerateOtpResult' => [\Otp\Application\UseCase\Command\Challenge\GenerateOtp\GenerateOtpResult::class];
    yield 'Otp.ResendChallengeCommand' => [\Otp\Application\UseCase\Command\Challenge\ResendChallenge\ResendChallengeCommand::class];
    yield 'Otp.ResendChallengeResult' => [\Otp\Application\UseCase\Command\Challenge\ResendChallenge\ResendChallengeResult::class];
    yield 'Otp.VerifyOtpCommand' => [\Otp\Application\UseCase\Command\Challenge\VerifyOtp\VerifyOtpCommand::class];
    yield 'Otp.SetupTotpCommand' => [\Otp\Application\UseCase\Command\Totp\SetupTotp\SetupTotpCommand::class];
    yield 'Otp.SetupTotpResult' => [\Otp\Application\UseCase\Command\Totp\SetupTotp\SetupTotpResult::class];
    yield 'Otp.GetChallengeStatusQuery' => [\Otp\Application\UseCase\Query\Challenge\GetChallengeStatus\GetChallengeStatusQuery::class];
    yield 'Otp.GetChallengeStatusResult' => [\Otp\Application\UseCase\Query\Challenge\GetChallengeStatus\GetChallengeStatusResult::class];
    yield 'Otp.ListChannelsResult' => [\Otp\Application\UseCase\Query\Config\ListChannels\ListChannelsResult::class];
    yield 'Otp.ChannelResult' => [\Otp\Application\UseCase\Query\Config\ListChannels\ChannelResult::class];
    yield 'Otp.ListPurposesResult' => [\Otp\Application\UseCase\Query\Config\ListPurposes\ListPurposesResult::class];
    yield 'Otp.PurposeResult' => [\Otp\Application\UseCase\Query\Config\ListPurposes\PurposeResult::class];
    yield 'Otp.ChallengeInfo' => [\Otp\Application\Contract\Challenge\ChallengeInfo::class];
    yield 'Otp.VerificationInfo' => [\Otp\Application\Contract\Challenge\VerificationInfo::class];

    yield 'OAuth.GrantConsentCommand' => [\OAuth\Application\UseCase\Command\Consent\GrantConsent\GrantConsentCommand::class];
    yield 'OAuth.GrantConsentResult' => [\OAuth\Application\UseCase\Command\Consent\GrantConsent\GrantConsentResult::class];
    yield 'OAuth.IssueTokenCommand' => [\OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenCommand::class];
    yield 'OAuth.IssueTokenResult' => [\OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenResult::class];
    yield 'OAuth.ActivateClientCommand' => [\OAuth\Application\UseCase\Command\Client\ActivateClient\ActivateClientCommand::class];
    yield 'OAuth.DeactivateClientCommand' => [\OAuth\Application\UseCase\Command\Client\DeactivateClient\DeactivateClientCommand::class];
    yield 'OAuth.DeleteClientCommand' => [\OAuth\Application\UseCase\Command\Client\DeleteClient\DeleteClientCommand::class];
    yield 'OAuth.RegenerateClientSecretCommand' => [\OAuth\Application\UseCase\Command\Client\RegenerateClientSecret\RegenerateClientSecretCommand::class];
    yield 'OAuth.RegenerateClientSecretResult' => [\OAuth\Application\UseCase\Command\Client\RegenerateClientSecret\RegenerateClientSecretResult::class];
    yield 'OAuth.RegisterClientCommand' => [\OAuth\Application\UseCase\Command\Client\RegisterClient\RegisterClientCommand::class];
    yield 'OAuth.RegisterClientResult' => [\OAuth\Application\UseCase\Command\Client\RegisterClient\RegisterClientResult::class];
    yield 'OAuth.UpdateClientDetailsCommand' => [\OAuth\Application\UseCase\Command\Client\UpdateClientDetails\UpdateClientDetailsCommand::class];
    yield 'OAuth.CheckConsentQuery' => [\OAuth\Application\UseCase\Query\Consent\CheckConsent\CheckConsentQuery::class];
    yield 'OAuth.CheckConsentResult' => [\OAuth\Application\UseCase\Query\Consent\CheckConsent\CheckConsentResult::class];
    yield 'OAuth.RefreshTokenQuery' => [\OAuth\Application\UseCase\Query\Token\RefreshToken\RefreshTokenQuery::class];
    yield 'OAuth.RefreshTokenResult' => [\OAuth\Application\UseCase\Query\Token\RefreshToken\RefreshTokenResult::class];
    yield 'OAuth.ValidateTokenQuery' => [\OAuth\Application\UseCase\Query\Token\ValidateToken\ValidateTokenQuery::class];
    yield 'OAuth.ValidateTokenResult' => [\OAuth\Application\UseCase\Query\Token\ValidateToken\ValidateTokenResult::class];
    yield 'OAuth.GetClientQuery' => [\OAuth\Application\UseCase\Query\Client\GetClient\GetClientQuery::class];
    yield 'OAuth.GetClientResult' => [\OAuth\Application\UseCase\Query\Client\GetClient\GetClientResult::class];
    yield 'OAuth.ListClientsQuery' => [\OAuth\Application\UseCase\Query\Client\ListClients\ListClientsQuery::class];
    yield 'OAuth.ListClientsResult' => [\OAuth\Application\UseCase\Query\Client\ListClients\ListClientsResult::class];
    yield 'OAuth.ValidateClientCredentialsQuery' => [\OAuth\Application\UseCase\Query\Client\ValidateClientCredentials\ValidateClientCredentialsQuery::class];
    yield 'OAuth.ValidateClientCredentialsResult' => [\OAuth\Application\UseCase\Query\Client\ValidateClientCredentials\ValidateClientCredentialsResult::class];
    yield 'OAuth.CheckConsentOutput' => [\OAuth\Presentation\Api\Dto\Output\Consent\CheckConsentOutput::class];
    yield 'OAuth.LeagueClientEntity' => [\OAuth\Infrastructure\OAuth2\League\Entity\Client::class];

    yield 'Authorization.CreatePermissionCommand' => [\Authorization\Application\UseCase\Command\Permission\CreatePermission\CreatePermissionCommand::class];
    yield 'Authorization.CreatePermissionResult' => [\Authorization\Application\UseCase\Command\Permission\CreatePermission\CreatePermissionResult::class];
    yield 'Authorization.UpdatePermissionCommand' => [\Authorization\Application\UseCase\Command\Permission\UpdatePermission\UpdatePermissionCommand::class];
    yield 'Authorization.UpdatePermissionResult' => [\Authorization\Application\UseCase\Command\Permission\UpdatePermission\UpdatePermissionResult::class];
    yield 'Authorization.DeletePermissionCommand' => [\Authorization\Application\UseCase\Command\Permission\DeletePermission\DeletePermissionCommand::class];
    yield 'Authorization.DeletePermissionResult' => [\Authorization\Application\UseCase\Command\Permission\DeletePermission\DeletePermissionResult::class];
    yield 'Authorization.CreateRoleCommand' => [\Authorization\Application\UseCase\Command\Role\CreateRole\CreateRoleCommand::class];
    yield 'Authorization.CreateRoleResult' => [\Authorization\Application\UseCase\Command\Role\CreateRole\CreateRoleResult::class];
    yield 'Authorization.UpdateRoleCommand' => [\Authorization\Application\UseCase\Command\Role\UpdateRole\UpdateRoleCommand::class];
    yield 'Authorization.UpdateRoleResult' => [\Authorization\Application\UseCase\Command\Role\UpdateRole\UpdateRoleResult::class];
    yield 'Authorization.DeleteRoleCommand' => [\Authorization\Application\UseCase\Command\Role\DeleteRole\DeleteRoleCommand::class];
    yield 'Authorization.DeleteRoleResult' => [\Authorization\Application\UseCase\Command\Role\DeleteRole\DeleteRoleResult::class];
    yield 'Authorization.AddPermissionToRoleCommand' => [\Authorization\Application\UseCase\Command\Role\AddPermissionToRole\AddPermissionToRoleCommand::class];
    yield 'Authorization.AddPermissionToRoleResult' => [\Authorization\Application\UseCase\Command\Role\AddPermissionToRole\AddPermissionToRoleResult::class];
    yield 'Authorization.RemovePermissionFromRoleCommand' => [\Authorization\Application\UseCase\Command\Role\RemovePermissionFromRole\RemovePermissionFromRoleCommand::class];
    yield 'Authorization.RemovePermissionFromRoleResult' => [\Authorization\Application\UseCase\Command\Role\RemovePermissionFromRole\RemovePermissionFromRoleResult::class];
    yield 'Authorization.GetPermissionQuery' => [\Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionQuery::class];
    yield 'Authorization.GetPermissionResult' => [\Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult::class];
    yield 'Authorization.ListPermissionsQuery' => [\Authorization\Application\UseCase\Query\Permission\ListPermissions\ListPermissionsQuery::class];
    yield 'Authorization.ListPermissionsResult' => [\Authorization\Application\UseCase\Query\Permission\ListPermissions\ListPermissionsResult::class];
    yield 'Authorization.GetRoleQuery' => [\Authorization\Application\UseCase\Query\Role\GetRole\GetRoleQuery::class];
    yield 'Authorization.GetRoleResult' => [\Authorization\Application\UseCase\Query\Role\GetRole\GetRoleResult::class];
    yield 'Authorization.ListRolesQuery' => [\Authorization\Application\UseCase\Query\Role\ListRoles\ListRolesQuery::class];
    yield 'Authorization.ListRolesResult' => [\Authorization\Application\UseCase\Query\Role\ListRoles\ListRolesResult::class];

    yield 'Shared.Pagination' => [\Shared\Application\Contract\Pagination\Pagination::class];
    yield 'Shared.PaginatedResult' => [\Shared\Application\Contract\Pagination\PaginatedResult::class];
  }
  // #endregion

  /**
   * @param ReflectionClass<object> $reflection
   *
   * @return array<int, mixed>
   */
  private function buildArgs(ReflectionClass $reflection, int $depth): array
  {
    $constructor = $reflection->getConstructor();
    if (null === $constructor) {
      return [];
    }

    $args = [];
    foreach ($constructor->getParameters() as $param) {
      $args[] = $this->buildValue($param, $depth);
    }

    return $args;
  }

  private function buildValue(ReflectionParameter $param, int $depth): mixed
  {
    $type = $param->getType();
    if ($type instanceof ReflectionUnionType) {
      foreach ($type->getTypes() as $unionType) {
        if ($unionType instanceof ReflectionNamedType && 'null' !== $unionType->getName()) {
          return $this->buildValueForNamedType($unionType, $param, $depth);
        }
      }

      return null;
    }

    if ($type instanceof ReflectionNamedType) {
      return $this->buildValueForNamedType($type, $param, $depth);
    }

    return null;
  }

  private function buildValueForNamedType(ReflectionNamedType $type, ReflectionParameter $param, int $depth): mixed
  {
    $name = $type->getName();
    $index = $param->getPosition() + 1;

    if ('string' === $name) {
      return $param->getName() . '-' . $index;
    }

    if ('int' === $name) {
      return 1;
    }

    if ('bool' === $name) {
      return true;
    }

    if ('float' === $name) {
      return 1.0;
    }

    if ('array' === $name) {
      return $this->buildArrayValue($param);
    }

    if ('mixed' === $name) {
      return $param->getName() . '-' . $index;
    }

    if (is_a($name, DateTimeInterface::class, true)) {
      return new DateTimeImmutable('2024-01-01T00:00:00+00:00');
    }

    if (enum_exists($name)) {
      $cases = $name::cases();

      return $cases[0];
    }

    if (GrantTypes::class === $name) {
      return new GrantTypes(GrantType::AUTHORIZATION_CODE);
    }

    if (Scopes::class === $name) {
      return Scopes::fromArray(['OPENID']);
    }

    if (RedirectUri::class === $name) {
      return new RedirectUri('https://client.example.com/callback');
    }

    if (TenantSettings::class === $name) {
      return new TenantSettings();
    }

    if (is_subclass_of($name, Uuid::class)) {
      return new $name('00000000-0000-4000-a000-000000000001');
    }

    if (class_exists($name)) {
      $classReflection = new ReflectionClass($name);
      if (!$classReflection->isInstantiable()) {
        return null;
      }

      if ($depth >= 2) {
        return $classReflection->newInstanceWithoutConstructor();
      }

      $args = $this->buildArgs($classReflection, $depth + 1);

      return $classReflection->newInstanceArgs($args);
    }

    return $type->allowsNull() ? null : $param->getName() . '-' . $index;
  }

  /**
   * @return array<int|string, mixed>
   */
  private function buildArrayValue(ReflectionParameter $param): array
  {
    $name = $param->getName();

    if (str_contains($name, 'redirectUris')) {
      return [new RedirectUri('https://client.example.com/callback')];
    }

    if (str_contains($name, 'scopes')) {
      return ['openid'];
    }

    if (str_contains($name, 'permissionIds')) {
      return ['perm-1'];
    }

    if (str_contains($name, 'roleIds')) {
      return ['role-1'];
    }

    return [];
  }
}
