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
		'ordered_class_elements' => [
			'order' => [
				'use_trait',
				'case',
				'constant_public',
				'constant_protected',
				'constant_private',
				'property_public',
				'property_protected',
				'property_private',
				'construct',
				'destruct',
				'magic',
				'phpunit',
				'method_public',
				'method_protected',
				'method_private',
			],
			'sort_algorithm' => 'none',
		],
		'ordered_traits' => true,
		'modifier_keywords' => true,
		'single_quote' => true,
		'no_trailing_comma_in_singleline_array' => true,
		'ordered_imports' => [
			'imports_order' => ['class', 'function', 'const'],
			'sort_algorithm' => 'alpha',
		],
		'single_import_per_statement' => false,
		'group_import' => [
			'group_types' => ['classy', 'functions', 'constants'],
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
		'native_constant_invocation' => [
			'scope' => 'namespaced',
			'strict' => true,
		],
		'single_line_throw' => false,
		'blank_line_before_statement' => true,
		'phpdoc_to_comment' => false,
		'phpdoc_align' => ['align' => 'left'],
		'phpdoc_separation' => true,
		'phpdoc_trim' => true,
		'phpdoc_order' => true,
		'phpdoc_line_span' => [
			'const' => 'multi',
			'property' => 'multi',
			'method' => 'multi',
		],
		'no_superfluous_phpdoc_tags' => true,
		'phpdoc_add_missing_param_annotation' => true,
		'phpdoc_return_self_reference' => true,
		'strict_param' => true,
		'strict_comparison' => true,
		'phpdoc_to_param_type' => true,
		'phpdoc_to_return_type' => true,
		'phpdoc_to_property_type' => true,
		'no_alias_functions' => true,
		'no_unneeded_control_parentheses' => true,
		'standardize_not_equals' => true,
		'no_useless_return' => true,
		'no_superfluous_elseif' => true,
		'no_useless_else' => true,
		'combine_consecutive_issets' => true,
		'combine_consecutive_unsets' => true,
		'new_expression_parentheses' => ['use_parentheses' => false],
		'simplified_if_return' => true,
		'simplified_null_return' => true,
		'binary_operator_spaces' => true,
		'phpdoc_order_by_value' => true,
		'concat_space' => ['spacing' => 'one'],
	])
	->setIndent('  ')
	->setLineEnding("\n")
	->setUsingCache(true)
	->setFinder($finder)
	->setRiskyAllowed(true)
;
