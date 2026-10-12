<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Adapter\Import;

use Import\Application\Contract\ImportRowValidationException;
use Organization\Application\Contract\Provisioning\ProvisionMemberInvitationRequest;
use Organization\Infrastructure\Import\MemberImportRowFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MemberImportRowFactoryTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MemberImportRowFactory::class)]
final class MemberImportRowFactoryTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f67a01';

  private const string INVITED_BY = '018f0b68-6758-7a12-8a1d-3f0d97f67a02';

  #[Test]
  public function itMapsAFullyPopulatedRow(): void
  {
    $request = new MemberImportRowFactory()->map(self::ORGANIZATION_ID, self::INVITED_BY, [
      'email' => 'alice@example.com',
      'roles' => 'admin|manager',
    ]);

    self::assertInstanceOf(ProvisionMemberInvitationRequest::class, $request);
    self::assertSame(self::ORGANIZATION_ID, $request->organizationId);
    self::assertSame('alice@example.com', $request->email);
    self::assertSame(self::INVITED_BY, $request->invitedByUserId);
    self::assertSame(['admin', 'manager'], $request->roleNames);
    self::assertFalse($request->dryRun);
  }

  #[Test]
  public function itTrimsRoleNamesAndDropsBlankSegments(): void
  {
    $request = new MemberImportRowFactory()->map(self::ORGANIZATION_ID, self::INVITED_BY, [
      'email' => '  alice@example.com  ',
      'roles' => ' admin | manager ||  ',
    ]);

    self::assertSame('alice@example.com', $request->email);
    self::assertSame(['admin', 'manager'], $request->roleNames);
  }

  #[Test]
  public function itTreatsABlankOrAbsentRolesCellAsTheDefaultRoleFallback(): void
  {
    $absent = new MemberImportRowFactory()->map(self::ORGANIZATION_ID, self::INVITED_BY, ['email' => 'a@example.com']);
    $blank = new MemberImportRowFactory()->map(self::ORGANIZATION_ID, self::INVITED_BY, ['email' => 'a@example.com', 'roles' => '   ']);

    self::assertSame([], $absent->roleNames);
    self::assertSame([], $blank->roleNames);
  }

  #[Test]
  public function itRejectsAMissingOrBlankEmail(): void
  {
    try {
      new MemberImportRowFactory()->map(self::ORGANIZATION_ID, self::INVITED_BY, ['roles' => 'admin']);
      self::fail('Expected an ImportRowValidationException for the missing email column.');
    } catch (ImportRowValidationException $exception) {
      self::assertSame('missing_required', $exception->errorCode);
      self::assertSame('email', $exception->column);
    }

    $this->expectException(ImportRowValidationException::class);
    new MemberImportRowFactory()->map(self::ORGANIZATION_ID, self::INVITED_BY, ['email' => '   ']);
  }

  #[Test]
  public function itLeavesEmailSyntaxValidationToTheProvisioningPort(): void
  {
    // "not-an-email" is structurally present (non-blank); syntax is validated
    // by MemberInvitationProvisioningService, which reports it as a
    // non-fatal `invalid` outcome.
    $request = new MemberImportRowFactory()->map(self::ORGANIZATION_ID, self::INVITED_BY, ['email' => 'not-an-email']);

    self::assertSame('not-an-email', $request->email);
  }
}
