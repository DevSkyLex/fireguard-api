<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Validator\ValidRedirectUri;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Constraint ValidRedirectUri.
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidRedirectUri extends Constraint
{
    // #region Properties
    /**
     * Property messageInvalid.
     */
    public string $messageInvalid = 'The redirect URI "{{ uri }}" is not a valid URL.';

    /**
     * Property messageScheme.
     */
    public string $messageScheme = 'The redirect URI "{{ uri }}" must use HTTPS (except for localhost).';

    /**
     * Property messageFragment.
     */
    public string $messageFragment = 'The redirect URI "{{ uri }}" must not contain a fragment.';

    /**
     * Property allowLocalhost.
     *
     * Whether to allow HTTP for localhost.
     */
    public bool $allowLocalhost = true;
    // #endregion
}
