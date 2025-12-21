<?php

$finder = (new PhpCsFixer\Finder())
	->in(__DIR__)
	->exclude(['var', 'vendor', 'public', 'migrations'])
;
	
return (new PhpCsFixer\Config())
	->setRules([
		'@Symfony' => true,
		'@PSR12' => true,
		'declare_strict_types' => true,
		'array_syntax' => ['syntax' => 'short'],
		'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
		'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline', 'keep_multiple_spaces_after_comma' => false],
		'class_attributes_separation' => ['elements' => ['method' => 'one', 'property' => 'one', 'const' => 'one']],
		'single_quote' => true,
		'no_trailing_comma_in_singleline_array' => true,
		'ordered_imports' => [
			'imports_order' => ['class', 'function', 'const'],
			'sort_algorithm' => 'alpha',
		],
		'no_unused_imports' => true,
		// Enforce use instead of FQN
		'fully_qualified_strict_types' => true,
		// Global namespace imports
		'global_namespace_import' => [
			'import_classes' => true,
			'import_constants' => true,
			'import_functions' => true,
		],
		// Performance optimization for native functions
		'native_function_invocation' => [
			'include' => ['@internal'],
			'scope' => 'namespaced',
			'strict' => true,
		],
		'single_line_throw' => false,
		'phpdoc_to_comment' => false,
		'phpdoc_align' => ['align' => 'left'],
		'phpdoc_separation' => true,
		'phpdoc_trim' => true,
		'phpdoc_order' => true,
		'no_superfluous_phpdoc_tags' => true,
		'phpdoc_add_missing_param_annotation' => true,
		'phpdoc_return_self_reference' => true,
		'strict_param' => true,
		'strict_comparison' => true,
		'phpdoc_order_by_value' => true,
		'concat_space' => ['spacing' => 'one'],
	])
	->setIndent('  ')
	->setLineEnding("\n")
	->setUsingCache(true)
	->setFinder($finder)
	->setRiskyAllowed(true)
;
