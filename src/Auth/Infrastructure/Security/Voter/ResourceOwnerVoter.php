<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Security\Voter;

use Auth\Infrastructure\Security\User\SecurityUser;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\{Vote, Voter};

use function in_array;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function property_exists;

/**
 * Class ResourceOwnerVoter
 *
 * Grants ownership attributes only when the current SecurityUser matches an owner identifier resolved from the subject.
 *
 * @category Voter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @extends Voter<string, object>
 */
#[AutoconfigureTag(name: 'security.voter')]
final class ResourceOwnerVoter extends Voter
{
  // #region Constants
  /**
   * Constant OWNER
   *
   * Authorization attribute that checks whether the current user owns the subject.
   *
   * @access public
   */
  public const string OWNER = 'OWNER';

  /**
   * Constant VIEW_OWN
   *
   * Authorization attribute for viewing a subject owned by the current user.
   *
   * @access public
   */
  public const string VIEW_OWN = 'VIEW_OWN';

  /**
   * Constant EDIT_OWN
   *
   * Authorization attribute for editing a subject owned by the current user.
   *
   * @access public
   */
  public const string EDIT_OWN = 'EDIT_OWN';

  /**
   * Constant DELETE_OWN
   *
   * Authorization attribute for deleting a subject owned by the current user.
   *
   * @access public
   */
  public const string DELETE_OWN = 'DELETE_OWN';
  // #endregion

  // #region Methods
  /**
   * Method supports
   *
   * Handles only ownership attributes for object subjects.
   *
   * @access protected
   *
   * @param string $attribute requested voter attribute
   * @param mixed $subject object whose ownership is being checked
   *
   * @return bool whether this voter supports the attribute and subject
   */
  protected function supports(string $attribute, mixed $subject): bool
  {
    return in_array($attribute, [self::OWNER, self::VIEW_OWN, self::EDIT_OWN, self::DELETE_OWN], true)
      && is_object($subject);
  }

  /**
   * Method voteOnAttribute
   *
   * Grants the vote only when the authenticated SecurityUser identifier matches an owner identifier on the subject.
   *
   * @access protected
   *
   * @param string $attribute requested ownership attribute
   * @param object $subject resource whose owner is resolved
   * @param TokenInterface $token current security token
   * @param ?Vote $vote optional vote context supplied by Symfony
   *
   * @return bool whether the current user owns the subject
   */
  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
  {
    $user = $token->getUser();

    if (!$user instanceof SecurityUser) {
      return false;
    }

    $ownerId = $this->getOwnerId($subject);

    if (null === $ownerId) {
      return false;
    }

    return $user->getId() === $ownerId;
  }

  /**
   * Method getOwnerId
   *
   * Resolves ownership through the supported owner/user accessors and converts scalar value-object IDs to strings.
   *
   * @access private
   *
   * @param object $subject resource whose owner is resolved
   *
   * @return ?string owner identifier, or null when no supported accessor yields one
   */
  private function getOwnerId(object $subject): ?string
  {
    $methods = ['getOwnerId', 'getUserId', 'ownerId', 'userId', 'getOwner', 'getUser'];

    foreach ($methods as $method) {
      if (!method_exists($subject, $method)) {
        continue;
      }
      $result = $subject->$method();
      if (is_object($result) && property_exists($result, 'value')) {
        $value = $result->value;

        return is_string($value) || is_int($value) ? (string) $value : null;
      }
      $ownerId = $this->printableOwnerId($result);
      if (null !== $ownerId) {
        return $ownerId;
      }
    }

    return null;
  }

  /**
   * Method printableOwnerId
   *
   * Converts stringable owner values or strings to the identifier form compared by the voter.
   *
   * @access private
   *
   * @param mixed $value candidate owner value
   *
   * @return ?string printable owner identifier, or null for unsupported values
   */
  private function printableOwnerId(mixed $value): ?string
  {
    if (is_object($value) && method_exists($value, '__toString')) {
      return (string) $value;
    }

    return is_string($value) ? $value : null;
  }
  // #endregion
}
