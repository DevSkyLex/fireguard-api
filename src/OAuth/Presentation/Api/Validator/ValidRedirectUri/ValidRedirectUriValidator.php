<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Validator\ValidRedirectUri;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

use function in_array;
use function is_array;
use function is_string;
use function parse_url;

/**
 * Validator ValidRedirectUriValidator.
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ValidRedirectUriValidator extends ConstraintValidator
{
    // #region Methods
    /**
     * Method validate
     * {@inheritDoc}
     *
     * Validates ValidRedirectUri constraint.
     *
     * @since 1.0.0
     *
     * @param mixed      $value      the value to validate
     * @param Constraint $constraint the constraint to validate against
     *
     * @return void no return value
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidRedirectUri) {
            throw new UnexpectedTypeException(
                value: $constraint,
                expectedType: ValidRedirectUri::class
            );
        }

        if (null === $value) {
            return;
        }

        $uris = is_array($value) ? $value : [$value];

        foreach ($uris as $uri) {
            if (!is_string($uri)) {
                continue;
            }

            $this->validateUri(
                uri: $uri,
                constraint: $constraint
            );
        }
    }

    /**
     * Method validateUri.
     *
     * Validates a single URI.
     *
     * @since 1.0.0
     *
     * @param string           $uri        the URI
     * @param ValidRedirectUri $constraint the constraint
     *
     * @return void no return value
     */
    private function validateUri(string $uri, ValidRedirectUri $constraint): void
    {
        $parsed = parse_url($uri);

        // Check if valid URL
        if (false === $parsed || !isset($parsed['scheme'], $parsed['host'])) {
            $this->context->buildViolation(message: $constraint->messageInvalid)
              ->setParameter('{{ uri }}', $uri)
              ->addViolation();

            return;
        }

        // Check for fragment
        if (isset($parsed['fragment'])) {
            $this->context->buildViolation(
                message: $constraint->messageFragment
            )
              ->setParameter('{{ uri }}', $uri)
              ->addViolation();

            return;
        }

        // Check HTTPS requirement
        $isLocalhost = in_array($parsed['host'], ['localhost', '127.0.0.1', '::1'], true);
        $isHttps = 'https' === $parsed['scheme'];

        if (!$isHttps && !($constraint->allowLocalhost && $isLocalhost)) {
            $this->context->buildViolation(message: $constraint->messageScheme)
              ->setParameter('{{ uri }}', $uri)
              ->addViolation();
        }
    }
    // #endregion
}
