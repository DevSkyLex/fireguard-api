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
 * Voter ResourceOwnerVoter.
 *
 * @category Voter
 *
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
  public const string OWNER = 'OWNER';

  public const string VIEW_OWN = 'VIEW_OWN';

  public const string EDIT_OWN = 'EDIT_OWN';

  public const string DELETE_OWN = 'DELETE_OWN';
  // #endregion

  // #region Methods
  protected function supports(string $attribute, mixed $subject): bool
  {
    return in_array($attribute, [self::OWNER, self::VIEW_OWN, self::EDIT_OWN, self::DELETE_OWN], true)
      && is_object($subject);
  }

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

  private function printableOwnerId(mixed $value): ?string
  {
    if (is_object($value) && method_exists($value, '__toString')) {
      return (string) $value;
    }

    return is_string($value) ? $value : null;
  }
  // #endregion
}
