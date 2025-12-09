<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validator ValidRedirectUriValidator
 * @final
 *
 * Validates ValidRedirectUri constraint.
 *
 * @category Validator
 * @package Shared\Presentation\Api\Validator
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ValidRedirectUriValidator extends ConstraintValidator
{
  //#region Methods
  /**
   * Method validate
   * {@inheritDoc}
   */
  public function validate(mixed $value, Constraint $constraint): void
  {
    if (!$constraint instanceof ValidRedirectUri) {
      throw new UnexpectedTypeException(value: $constraint, expectedType: ValidRedirectUri::class);
    }

    if ($value === null) {
      return;
    }

    $uris = is_array($value) ? $value : [$value];

    foreach ($uris as $uri) {
      if (!is_string($uri)) {
        continue;
      }

      $this->validateUri(uri: $uri, constraint: $constraint);
    }
  }

  /**
   * Method validateUri
   *
   * Validates a single URI.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $uri The URI.
   * @param ValidRedirectUri $constraint The constraint.
   *
   * @return void
   */
  private function validateUri(string $uri, ValidRedirectUri $constraint): void
  {
    $parsed = parse_url($uri);

    // Check if valid URL
    if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
      $this->context->buildViolation(message: $constraint->messageInvalid)
        ->setParameter('{{ uri }}', $uri)
        ->addViolation();
      return;
    }

    // Check for fragment
    if (isset($parsed['fragment'])) {
      $this->context->buildViolation(message: $constraint->messageFragment)
        ->setParameter('{{ uri }}', $uri)
        ->addViolation();
      return;
    }

    // Check HTTPS requirement
    $isLocalhost = in_array($parsed['host'], ['localhost', '127.0.0.1', '::1'], true);
    $isHttps = $parsed['scheme'] === 'https';

    if (!$isHttps && !($constraint->allowLocalhost && $isLocalhost)) {
      $this->context->buildViolation(message: $constraint->messageScheme)
        ->setParameter('{{ uri }}', $uri)
        ->addViolation();
    }
  }
  //#endregion
}
