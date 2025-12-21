<?php

$finder = (new PhpCsFixer\Finder())
	->in(__DIR__)
	->exclude(['var', 'vendor', 'public', 'migrations'])
;
	
return (new PhpCsFixer\Config())
	->setRules([
		'@Symfony' => true,
		'@PSR12' => true,
		'array_syntax' => ['syntax' => 'short'],
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
		'concat_space' => ['spacing' => 'one'],
	])
	->setIndent('  ')
	->setLineEnding("\n")
	->setUsingCache(true)
	->setFinder($finder)
	->setRiskyAllowed(true)
;
