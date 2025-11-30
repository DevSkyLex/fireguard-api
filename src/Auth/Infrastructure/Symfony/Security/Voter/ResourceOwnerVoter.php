<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Symfony\Security\Voter;

use Auth\Infrastructure\Symfony\Security\SecurityUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

use function in_array;
use function is_object;

/**
 * Voter ResourceOwnerVoter
 * @final
 *
 * Symfony Security Voter for resource ownership.
 * Checks if the authenticated user owns the resource.
 *
 * Usage in controllers:
 *   $this->denyAccessUnlessGranted('OWNER', $resource);
 *
 * The resource must implement a method to get the owner ID:
 *   - getOwnerId(): string
 *   - getUserId(): string
 *   - ownerId(): string
 *   - userId(): string
 *
 * @category Security
 * @package Auth\Infrastructure\Symfony\Security\Voter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @extends Voter<string, object>
 */
final class ResourceOwnerVoter extends Voter
{
  //#region Constants
  /**
   * Constant OWNER
   *
   * Attribute for owner check.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  public const string OWNER = 'OWNER';

  /**
   * Constant VIEW_OWN
   *
   * Attribute for viewing own resources.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  public const string VIEW_OWN = 'VIEW_OWN';

  /**
   * Constant EDIT_OWN
   *
   * Attribute for editing own resources.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  public const string EDIT_OWN = 'EDIT_OWN';

  /**
   * Constant DELETE_OWN
   *
   * Attribute for deleting own resources.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  public const string DELETE_OWN = 'DELETE_OWN';
  //#endregion

  //#region Methods
  /**
   * Method supports
   * {@inheritDoc}
   *
   * Determines if the attribute and subject are supported by this voter.
   *
   * @access protected
   * @since 1.0.0
   *
   * @param string $attribute The attribute.
   * @param mixed $subject The subject.
   *
   * @return bool True if the attribute is supported.
   */
  protected function supports(string $attribute, mixed $subject): bool
  {
    return in_array($attribute, [self::OWNER, self::VIEW_OWN, self::EDIT_OWN, self::DELETE_OWN], true)
      && is_object($subject);
  }

  /**
   * Method voteOnAttribute
   * {@inheritDoc}
   *
   * Perform a single access check operation on a given attribute, subject and token.
   *
   * @access protected
   * @since 1.0.0
   *
   * @param string $attribute The attribute.
   * @param mixed $subject The subject.
   * @param TokenInterface $token The token.
   *
   * @return bool True if access is granted.
   */
  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
  {
    $user = $token->getUser();

    if (!$user instanceof SecurityUser) {
      return false;
    }

    $ownerId = $this->getOwnerId($subject);

    if ($ownerId === null) {
      return false;
    }

    return $user->getId() === $ownerId;
  }

  /**
   * Method getOwnerId
   *
   * Extracts the owner ID from the subject.
   *
   * @access private
   * @since 1.0.0
   *
   * @param object $subject The subject.
   *
   * @return string|null The owner ID or null if not found.
   */
  private function getOwnerId(object $subject): ?string
  {
    // Try different method names
    $methods = ['getOwnerId', 'getUserId', 'ownerId', 'userId', 'getOwner', 'getUser'];

    foreach ($methods as $method) {
      if (method_exists($subject, $method)) {
        $result = $subject->$method();

        // Handle Value Objects with a value property
        if (is_object($result) && property_exists($result, 'value')) {
          return (string) $result->value;
        }

        // Handle objects with __toString
        if (is_object($result) && method_exists($result, '__toString')) {
          return (string) $result;
        }

        if (is_string($result)) {
          return $result;
        }
      }
    }

    return null;
  }
  //#endregion
}
