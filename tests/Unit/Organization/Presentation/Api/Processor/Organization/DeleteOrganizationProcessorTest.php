<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Delete;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\DeleteOrganization\DeleteOrganizationCommand;
use Organization\Domain\Exception\{OrganizationDeletionConfirmationMismatchException, OrganizationNotFoundException};
use Organization\Presentation\Api\Processor\Organization\DeleteOrganizationProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException,
  UnprocessableEntityHttpException
};

#[CoversClass(DeleteOrganizationProcessor::class)]
final class DeleteOrganizationProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new DeleteOrganizationProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      requestStack: $this->emptyRequestStack(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenIdentifierMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $processor = new DeleteOrganizationProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      requestStack: $this->emptyRequestStack(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Delete(), []);
  }

  #[Test]
  public function testProcessThrowsWhenDeletePermissionMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.delete')
      ->willReturn(false);

    $processor = new DeleteOrganizationProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $authorization,
      security: $security,
      requestStack: $this->emptyRequestStack(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessForwardsSlugQueryParameterAndReturnsNull(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (DeleteOrganizationCommand $command): bool {
        return self::ORGANIZATION_ID === $command->organizationId
          && 'fireguard-nice' === $command->slugConfirmation;
      }));

    $processor = new DeleteOrganizationProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->requestStackWithSlug('fireguard-nice'),
    );

    $result = $processor->process(null, new Delete(), ['id' => self::ORGANIZATION_ID]);

    self::assertNull($result);
  }

  #[Test]
  public function testProcessForwardsNullSlugConfirmationWhenQueryParameterAbsent(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (DeleteOrganizationCommand $command): bool {
        return self::ORGANIZATION_ID === $command->organizationId
          && null === $command->slugConfirmation;
      }))
      ->willThrowException(OrganizationDeletionConfirmationMismatchException::missing());

    $processor = new DeleteOrganizationProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->emptyRequestStack(),
    );

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process(null, new Delete(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsUnprocessableEntityWhenConfirmationMismatched(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationDeletionConfirmationMismatchException::mismatched());

    $processor = new DeleteOrganizationProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->requestStackWithSlug('wrong-slug'),
    );

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process(null, new Delete(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenOrganizationAbsent(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationNotFoundException::withId(self::ORGANIZATION_ID));

    $processor = new DeleteOrganizationProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->requestStackWithSlug('fireguard-nice'),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Delete(), ['id' => self::ORGANIZATION_ID]);
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }

  private function emptyRequestStack(): RequestStack
  {
    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    return $requestStack;
  }

  private function requestStackWithSlug(string $slug): RequestStack
  {
    $request = new Request();
    $request->query->set('slug', $slug);

    $requestStack = new RequestStack();
    $requestStack->push($request);

    return $requestStack;
  }
}
