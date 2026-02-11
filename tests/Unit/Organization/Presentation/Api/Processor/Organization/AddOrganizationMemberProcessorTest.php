<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\AddOrganizationMember\{AddOrganizationMemberCommand, AddOrganizationMemberResult};
use Organization\Presentation\Api\Dto\Input\Organization\AddOrganizationMemberInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Processor\Organization\AddOrganizationMemberProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

#[CoversClass(AddOrganizationMemberProcessor::class)]
final class AddOrganizationMemberProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenUserLacksPermission(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441200'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441200', '550e8400-e29b-41d4-a716-446655441210', 'organization.members.manage')
      ->willReturn(false);

    $processor = new AddOrganizationMemberProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $input = new AddOrganizationMemberInput();
    $input->userId = '550e8400-e29b-41d4-a716-446655441201';

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($input, new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441210']);
  }

  #[Test]
  public function testProcessDispatchesCommandAndMapsOutput(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441200');
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (AddOrganizationMemberCommand $command): bool {
        return '550e8400-e29b-41d4-a716-446655441210' === $command->organizationId
          && '550e8400-e29b-41d4-a716-446655441201' === $command->userId
          && ['550e8400-e29b-41d4-a716-446655441211'] === $command->roleIds;
      }))
      ->willReturn(new AddOrganizationMemberResult(
        memberId: '550e8400-e29b-41d4-a716-446655441212',
        organizationId: '550e8400-e29b-41d4-a716-446655441210',
        userId: '550e8400-e29b-41d4-a716-446655441201',
        roleIds: ['550e8400-e29b-41d4-a716-446655441211'],
        isActive: true,
        joinedAt: new DateTimeImmutable('-2 days'),
      ));

    $processor = new AddOrganizationMemberProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $input = new AddOrganizationMemberInput();
    $input->userId = '550e8400-e29b-41d4-a716-446655441201';
    $input->roleIds = ['550e8400-e29b-41d4-a716-446655441211'];

    $output = $processor->process($input, new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441210']);

    self::assertInstanceOf(OrganizationMemberOutput::class, $output);
    self::assertSame('550e8400-e29b-41d4-a716-446655441212', $output->id);
    self::assertSame('550e8400-e29b-41d4-a716-446655441210', $output->organizationId);
    self::assertSame('550e8400-e29b-41d4-a716-446655441201', $output->userId);
    self::assertSame(['550e8400-e29b-41d4-a716-446655441211'], $output->roleIds);
    self::assertTrue($output->isActive);
  }

  #[Test]
  public function testProcessThrowsWhenOrganizationIdMissingInUri(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441200'));

    $processor = new AddOrganizationMemberProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new AddOrganizationMemberInput(), new Post(), []);
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
