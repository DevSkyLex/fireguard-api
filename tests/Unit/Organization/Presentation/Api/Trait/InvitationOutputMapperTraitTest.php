<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Trait;

use DateTimeImmutable;
use Organization\Application\UseCase\Command\Organization\InviteOrganizationMember\InviteOrganizationMemberResult;
use Organization\Application\UseCase\Command\Organization\ResendOrganizationInvitation\ResendOrganizationInvitationResult;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInvitationOutput;
use Organization\Presentation\Api\Trait\InvitationOutputMapperTrait;
use PHPUnit\Framework\Attributes\{CoversTrait, Test};
use PHPUnit\Framework\TestCase;
use Tests\Unit\Organization\Presentation\Api\Trait\Double\InvitationOutputMapper;

/**
 * Test InvitationOutputMapperTrait.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(InvitationOutputMapperTrait::class)]
final class InvitationOutputMapperTraitTest extends TestCase
{
  #[Test]
  public function testMapsAnInviteResultOntoTheOutput(): void
  {
    $output = $this->mapper()->map(new InviteOrganizationMemberResult(
      invitationId: 'invitation-1',
      organizationId: 'organization-1',
      email: 'member@example.com',
      status: 'pending',
      invitedByUserId: 'user-1',
      expiresAt: new DateTimeImmutable('2026-03-08T09:00:00+00:00'),
      createdAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-02T09:00:00+00:00'),
      roleIds: ['role-1', 'role-2'],
      acceptUrl: 'https://app.example.com/accept/token',
    ));

    self::assertInstanceOf(OrganizationInvitationOutput::class, $output);
    self::assertSame('invitation-1', $output->id);
    self::assertSame('organization-1', $output->organizationId);
    self::assertSame('member@example.com', $output->email);
    self::assertSame('pending', $output->status);
    self::assertSame('user-1', $output->invitedByUserId);
    self::assertSame('2026-03-08T09:00:00+00:00', $output->expiresAt);
    self::assertSame('2026-03-01T09:00:00+00:00', $output->createdAt);
    self::assertSame('2026-03-02T09:00:00+00:00', $output->updatedAt);
    self::assertSame(['role-1', 'role-2'], $output->roleIds);
    self::assertSame('https://app.example.com/accept/token', $output->acceptUrl);
  }

  #[Test]
  public function testMapsAResendResultOntoTheOutput(): void
  {
    $output = $this->mapper()->map(new ResendOrganizationInvitationResult(
      invitationId: 'invitation-2',
      organizationId: 'organization-1',
      email: 'other@example.com',
      status: 'pending',
      invitedByUserId: 'user-2',
      expiresAt: new DateTimeImmutable('2026-04-08T09:00:00+00:00'),
      createdAt: new DateTimeImmutable('2026-04-01T09:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-04-02T09:00:00+00:00'),
      roleIds: [],
      acceptUrl: '',
    ));

    self::assertSame('invitation-2', $output->id);
    self::assertSame('other@example.com', $output->email);
    self::assertSame([], $output->roleIds);
    self::assertSame('', $output->acceptUrl);
  }

  /**
   * Builds a host object exposing the trait helper.
   */
  private function mapper(): InvitationOutputMapper
  {
    return new InvitationOutputMapper();
  }
}
