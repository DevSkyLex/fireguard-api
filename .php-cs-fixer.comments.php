<?php

declare(strict_types=1);

return (new PhpCsFixer\Config())
  ->setRules([
    'phpdoc_align' => ['align' => 'left'],
    'phpdoc_indent' => true,
    'phpdoc_separation' => [
      'groups' => [
        ['category', 'package', 'subpackage', 'version'],
        ['access', 'since'],
        ['author', 'copyright', 'license'],
        ['property', 'property-read', 'property-write'],
      ],
      'skip_unlisted_annotations' => false,
    ],
    'phpdoc_trim' => true,
    'phpdoc_order' => [
      'order' => ['category', 'package', 'subpackage', 'version', 'access', 'since', 'author', 'template', 'param', 'return', 'throws'],
    ],
    'phpdoc_no_access' => false,
    'phpdoc_summary' => false,
    'phpdoc_line_span' => [
      'const' => 'multi',
      'property' => 'multi',
      'method' => 'multi',
    ],
    'phpdoc_trim_consecutive_blank_line_separation' => true,
    'phpdoc_single_line_var_spacing' => true,
  ])
  ->setIndent('  ')
  ->setLineEnding("\n")
  ->setUsingCache(false)
;
