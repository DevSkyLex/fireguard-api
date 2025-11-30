<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Symfony\Security\Voter;

use Auth\Infrastructure\Symfony\Security\SecurityUser;
use Auth\Infrastructure\Symfony\Security\Voter\ResourceOwnerVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Class ResourceOwnerVoterTest
 *
 * Unit tests for the ResourceOwnerVoter.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Infrastructure\Symfony\Security\Voter
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ResourceOwnerVoter::class)]
final class ResourceOwnerVoterTest extends TestCase
{
  //#region Properties
  /**
   * Property voter
   *
   * ResourceOwnerVoter instance.
   *
   * @access private
   *
   * @var ResourceOwnerVoter
   */
  private ResourceOwnerVoter $voter;
  //#endregion

  //#region Methods
  /**
   * Method setUp
   *
   * Sets up the test environment.
   *
   * @access protected
   *
   * @return void
   */
  protected function setUp(): void
  {
    $this->voter = new ResourceOwnerVoter();
  }

  /**
   * Method testVoteGrantsAccessWhenUserOwnsResource
   *
   * Tests that vote grants access when user owns the resource.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testVoteGrantsAccessWhenUserOwnsResource(): void
  {
    $userId = 'user-123';

    $user = new SecurityUser(
      id: $userId,
      email: 'test@example.com',
      password: '',
      roles: ['ROLE_USER']
    );

    $resource = new class($userId) {
      public function __construct(private readonly string $ownerId) {}
      public function getOwnerId(): string { return $this->ownerId; }
    };

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $this->voter->vote($token, $resource, [ResourceOwnerVoter::OWNER]);

    $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
  }

  /**
   * Method testVoteDeniesAccessWhenUserDoesNotOwnResource
   *
   * Tests that vote denies access when user does not own the resource.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testVoteDeniesAccessWhenUserDoesNotOwnResource(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: '',
      roles: ['ROLE_USER']
    );

    $resource = new class('other-user-456') {
      public function __construct(private readonly string $ownerId) {}
      public function getOwnerId(): string { return $this->ownerId; }
    };

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $this->voter->vote($token, $resource, [ResourceOwnerVoter::OWNER]);

    $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
  }

  /**
   * Method testVoteAbstainsForNonOwnerAttributes
   *
   * Tests that vote abstains for non-owner attributes.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testVoteAbstainsForNonOwnerAttributes(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: '',
      roles: ['ROLE_USER']
    );

    $resource = new class('user-123') {
      public function __construct(private readonly string $ownerId) {}
      public function getOwnerId(): string { return $this->ownerId; }
    };

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $this->voter->vote($token, $resource, ['SOME_OTHER_ATTRIBUTE']);

    $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
  }

  /**
   * Method testVoteAbstainsForNonObjectSubject
   *
   * Tests that vote abstains when subject is not an object.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testVoteAbstainsForNonObjectSubject(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: '',
      roles: ['ROLE_USER']
    );

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $this->voter->vote($token, 'not-an-object', [ResourceOwnerVoter::OWNER]);

    $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
  }

  /**
   * Method testVoteDeniesAccessWhenUserIsNotSecurityUser
   *
   * Tests that vote denies access when user is not a SecurityUser.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testVoteDeniesAccessWhenUserIsNotSecurityUser(): void
  {
    $resource = new class('user-123') {
      public function __construct(private readonly string $ownerId) {}
      public function getOwnerId(): string { return $this->ownerId; }
    };

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn(null);

    $result = $this->voter->vote($token, $resource, [ResourceOwnerVoter::OWNER]);

    $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
  }

  /**
   * Method testVoteSupportsAllOwnerAttributes
   *
   * Tests that vote supports all owner-related attributes.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  #[DataProvider('ownerAttributesProvider')]
  public function testVoteSupportsAllOwnerAttributes(string $attribute): void
  {
    $userId = 'user-123';

    $user = new SecurityUser(
      id: $userId,
      email: 'test@example.com',
      password: '',
      roles: ['ROLE_USER']
    );

    $resource = new class($userId) {
      public function __construct(private readonly string $ownerId) {}
      public function getOwnerId(): string { return $this->ownerId; }
    };

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $this->voter->vote($token, $resource, [$attribute]);

    $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
  }

  /**
   * Method ownerAttributesProvider
   *
   * Data provider for owner attributes.
   *
   * @access public
   * @static
   *
   * @return iterable<string, array{string}>
   */
  public static function ownerAttributesProvider(): iterable
  {
    yield 'OWNER' => [ResourceOwnerVoter::OWNER];
    yield 'VIEW_OWN' => [ResourceOwnerVoter::VIEW_OWN];
    yield 'EDIT_OWN' => [ResourceOwnerVoter::EDIT_OWN];
    yield 'DELETE_OWN' => [ResourceOwnerVoter::DELETE_OWN];
  }

  /**
   * Method testVoteSupportsGetUserIdMethod
   *
   * Tests that vote supports getUserId method on resource.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testVoteSupportsGetUserIdMethod(): void
  {
    $userId = 'user-123';

    $user = new SecurityUser(
      id: $userId,
      email: 'test@example.com',
      password: '',
      roles: ['ROLE_USER']
    );

    $resource = new class($userId) {
      public function __construct(private readonly string $userId) {}
      public function getUserId(): string { return $this->userId; }
    };

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $this->voter->vote($token, $resource, [ResourceOwnerVoter::OWNER]);

    $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
  }

  /**
   * Method testVoteDeniesAccessWhenResourceHasNoOwnerMethod
   *
   * Tests that vote denies access when resource has no owner method.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testVoteDeniesAccessWhenResourceHasNoOwnerMethod(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: '',
      roles: ['ROLE_USER']
    );

    $resource = new class {
      public function getSomethingElse(): string { return 'value'; }
    };

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $this->voter->vote($token, $resource, [ResourceOwnerVoter::OWNER]);

    $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
  }
  //#endregion
}
