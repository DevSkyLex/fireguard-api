<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Provider\Discovery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use OAuth\Application\Port\Outbound\User\OidcUserProviderPort;
use OAuth\Application\Service\OidcClaimsBuilderInterface;
use OAuth\Presentation\Api\Dto\Output\Discovery\UserInfoOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\{HttpException, UnauthorizedHttpException};
use Throwable;

use function is_bool;
use function is_int;
use function is_string;
use function trim;

/**
 * Provider UserInfoProvider.
 *
 * @category Provider
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<UserInfoOutput>
 */
final readonly class UserInfoProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UserInfoProvider class.
   *
   * @since 1.0.0
   *
   * @param Security $security the Symfony Security service
   * @param OidcUserProviderPort $oidcUserProvider the OIDC user provider
   * @param OidcClaimsBuilderInterface $claimsBuilder the OIDC claims builder
   */
  public function __construct(
    private readonly Security $security,
    private readonly OidcUserProviderPort $oidcUserProvider,
    private readonly OidcClaimsBuilderInterface $claimsBuilder,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Provides user information based on the authenticated user.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return UserInfoOutput the user info
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserInfoOutput
  {
    $securityUser = $this->security->getUser();

    if (!$securityUser instanceof SecurityUser) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'Authentication required',
      );
    }

    if (!$securityUser->hasScope(scope: 'openid')) {
      throw new HttpException(
        statusCode: Response::HTTP_FORBIDDEN,
        message: 'Token does not have openid scope',
        headers: ['WWW-Authenticate' => 'Bearer error="insufficient_scope", scope="openid"'],
      );
    }

    try {
      $oidcUser = $this->oidcUserProvider->findByIdentifier($securityUser->getId());
      if (null === $oidcUser) {
        throw new UnauthorizedHttpException(
          challenge: 'Bearer',
          message: 'User not found',
        );
      }

      $claims = $this->claimsBuilder->buildUserInfoClaims(
        user: $oidcUser,
        scopes: $securityUser->getScopes(),
      );

      $output = new UserInfoOutput();
      $output->sub = $this->readStringClaim($claims, 'sub') ?? $securityUser->getId();
      $output->name = $this->readStringClaim($claims, 'name');
      $output->givenName = $this->readStringClaim($claims, 'given_name');
      $output->familyName = $this->readStringClaim($claims, 'family_name');
      $output->preferredUsername = $this->readStringClaim($claims, 'preferred_username');
      $output->picture = $this->readStringClaim($claims, 'picture');
      $output->email = $this->readStringClaim($claims, 'email');
      $output->emailVerified = $this->readBoolClaim($claims, 'email_verified');
      $output->updatedAt = $this->readIntClaim($claims, 'updated_at');

      return $output;

    } catch (UnauthorizedHttpException $exception) {
      throw $exception;
    } catch (Throwable $exception) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'Failed to get user info: ' . $exception->getMessage(),
      );
    }
  }

  /**
   * @param array<string, mixed> $claims
   */
  private function readStringClaim(array $claims, string $key): ?string
  {
    $value = $claims[$key] ?? null;
    if (!is_string($value)) {
      return null;
    }

    $trimmed = trim($value);
    if ('' === $trimmed) {
      return null;
    }

    return $trimmed;
  }

  /**
   * @param array<string, mixed> $claims
   */
  private function readBoolClaim(array $claims, string $key): ?bool
  {
    $value = $claims[$key] ?? null;
    if (!is_bool($value)) {
      return null;
    }

    return $value;
  }

  /**
   * @param array<string, mixed> $claims
   */
  private function readIntClaim(array $claims, string $key): ?int
  {
    $value = $claims[$key] ?? null;
    if (!is_int($value)) {
      return null;
    }

    return $value;
  }
  // #endregion
}
