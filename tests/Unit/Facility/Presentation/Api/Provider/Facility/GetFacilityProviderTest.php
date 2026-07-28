<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\UseCase\Query\Facility\GetFacility\{GetFacilityQuery, GetFacilityResult};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Provider\Facility\GetFacilityProvider;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

#[CoversClass(GetFacilityProvider::class)]
final class GetFacilityProviderTest extends TestCase
{
  #[Test]
  public function testProvideMapsWrappedFacilityNotFoundToHttp404(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441220';
    $facilityId = '550e8400-e29b-41d4-a716-446655441221';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441222');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.facilities.read')
      ->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      envelope: new Envelope(new GetFacilityQuery(
        organizationId: $organizationId,
        facilityId: $facilityId,
      )),
      exceptions: [FacilityNotFoundException::withId($facilityId)],
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new GetFacilityProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: [
        'organizationId' => $organizationId,
        'facilityId' => $facilityId,
      ],
    );
  }

  #[Test]
  public function testProvideMapsResultToOutput(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441230';
    $facilityId = '550e8400-e29b-41d4-a716-446655441231';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441232');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.facilities.read')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetFacilityQuery::class))
      ->willReturn(new GetFacilityResult(
        facilityId: $facilityId,
        organizationId: $organizationId,
        parentFacilityId: null,
        type: 'site',
        name: 'HQ',
        code: 'SITE-001',
        status: 'active',
        address: '10 rue',
        metadata: ['k' => 'v'],
        createdAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-02-12T10:10:00+00:00'),
        hasChildren: true,
        latitude: 48.8566,
        longitude: 2.3522,
      ));

    $provider = new GetFacilityProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(
      operation: new Get(),
      uriVariables: [
        'organizationId' => $organizationId,
        'facilityId' => $facilityId,
      ],
    );

    self::assertInstanceOf(FacilityOutput::class, $output);
    self::assertSame($facilityId, $output->id);
    self::assertSame('HQ', $output->name);
    self::assertTrue($output->hasChildren);
    self::assertSame(48.8566, $output->latitude);
    self::assertSame(2.3522, $output->longitude);
  }

  #[Test]
  public function testProvideRequiresAnAuthenticatedSecurityUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetFacilityProvider(
      queryBus: $queryBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $this->ask($provider);
  }

  #[Test]
  public function testProvideRejectsMissingUriVariables(): void
  {
    $provider = $this->makeProvider();

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('OrganizationId and facilityId URI parameters are required.');

    $provider->provide(operation: new Get(), uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655441240', 'facilityId' => '']);
  }

  #[Test]
  public function testProvideRejectsCallerWithoutReadPermission(): void
  {
    $provider = $this->makeProvider(hasPermission: false);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.facilities.read permission.');

    $this->ask($provider);
  }

  #[Test]
  public function testProvideMapsDirectNotFoundToHttp404(): void
  {
    $provider = $this->makeProvider(exception: FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441241'));

    $this->expectException(NotFoundHttpException::class);

    $this->ask($provider);
  }

  #[Test]
  public function testProvideMapsDirectInvalidArgumentToHttp400(): void
  {
    $provider = $this->makeProvider(exception: new InvalidArgumentException('Malformed identifier.'));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Malformed identifier.');

    $this->ask($provider);
  }

  #[Test]
  public function testProvideUnwrapsDirectlyWrappedNotFound(): void
  {
    $provider = $this->makeProvider(exception: MessengerRuntimeException::wrap(
      FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441241'),
    ));

    $this->expectException(NotFoundHttpException::class);

    $this->ask($provider);
  }

  #[Test]
  public function testProvideUnwrapsDirectlyWrappedInvalidArgument(): void
  {
    $provider = $this->makeProvider(exception: MessengerRuntimeException::wrap(
      new InvalidArgumentException('Wrapped invalid argument.'),
    ));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Wrapped invalid argument.');

    $this->ask($provider);
  }

  #[Test]
  public function testProvideUnwrapsHandlerWrappedInvalidArgument(): void
  {
    $provider = $this->makeProvider(exception: $this->handlerFailure(new InvalidArgumentException('Handler invalid argument.')));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Handler invalid argument.');

    $this->ask($provider);
  }

  #[Test]
  public function testProvideRethrowsUnrecognisedMessengerFailure(): void
  {
    $provider = $this->makeProvider(exception: MessengerRuntimeException::wrap(new RuntimeException('infrastructure down')));

    $this->expectException(MessengerRuntimeException::class);
    $this->expectExceptionMessage('infrastructure down');

    $this->ask($provider);
  }

  private function ask(GetFacilityProvider $provider): void
  {
    $provider->provide(
      operation: new Get(),
      uriVariables: [
        'organizationId' => '550e8400-e29b-41d4-a716-446655441240',
        'facilityId' => '550e8400-e29b-41d4-a716-446655441241',
      ],
    );
  }

  private function makeProvider(?Throwable $exception = null, bool $hasPermission = true): GetFacilityProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441239'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($hasPermission);

    $queryBus = $this->createStub(QueryBusPort::class);

    if (null !== $exception) {
      $queryBus->method('ask')->willThrowException($exception);
    }

    return new GetFacilityProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );
  }

  private function handlerFailure(Throwable $exception): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      envelope: new Envelope(new GetFacilityQuery(
        organizationId: '550e8400-e29b-41d4-a716-446655441240',
        facilityId: '550e8400-e29b-41d4-a716-446655441241',
      )),
      exceptions: [$exception],
    ));
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
