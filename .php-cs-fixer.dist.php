<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,

        // Array notation
        'array_syntax' => ['syntax' => 'short'],
        'array_indentation' => true,
        'normalize_index_brace' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'trim_array_spaces' => true,

        // Binary operators
        'binary_operator_spaces' => [
            'operators' => [
                '=>' => 'align_single_space_minimal',
                '=' => 'single_space',
            ],
        ],
        'concat_space' => ['spacing' => 'one'],

        // Blank lines
        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try', 'if', 'foreach', 'for', 'while', 'switch'],
        ],

        // Casing
        'lowercase_keywords' => true,
        'lowercase_static_reference' => true,
        'magic_constant_casing' => true,
        'magic_method_casing' => true,
        'native_function_casing' => true,
        'native_function_type_declaration_casing' => true,

        // Cast notation
        'cast_spaces' => ['space' => 'single'],
        'lowercase_cast' => true,
        'no_short_bool_cast' => true,
        'short_scalar_cast' => true,

        // Class notation
        'class_attributes_separation' => [
            'elements' => [
                'method' => 'one',
                'property' => 'one',
                'trait_import' => 'none',
            ],
        ],
        'class_definition' => [
            'single_line' => true,
            'single_item_single_line' => true,
            'multi_line_extends_each_single_line' => true,
        ],
        'no_blank_lines_after_class_opening' => true,
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
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
        ],
        'single_class_element_per_statement' => true,
        'visibility_required' => true,

        // Comments
        'multiline_comment_opening_closing' => true,
        'no_empty_comment' => true,
        'single_line_comment_style' => ['comment_types' => ['hash']],

        // Control structures
        'elseif' => true,
        'no_alternative_syntax' => true,
        'no_superfluous_elseif' => true,
        'no_unneeded_control_parentheses' => true,
        'no_useless_else' => true,
        'switch_case_semicolon_to_colon' => true,
        'switch_case_space' => true,
        'yoda_style' => false, // WordPress prefers non-Yoda style

        // Function notation
        'function_declaration' => ['closure_function_spacing' => 'one'],
        'function_typehint_space' => true,
        'lambda_not_used_import' => true,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'no_spaces_after_function_name' => true,
        'return_type_declaration' => ['space_before' => 'none'],
        'single_line_throw' => false,

        // Import
        'fully_qualified_strict_types' => true,
        'no_leading_import_slash' => true,
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'single_import_per_statement' => true,
        'single_line_after_imports' => true,

        // List notation
        'list_syntax' => ['syntax' => 'short'],

        // Namespace
        'no_leading_namespace_whitespace' => true,

        // PHP tag
        'blank_line_after_opening_tag' => true,
        'full_opening_tag' => true,
        'no_closing_tag' => true,

        // Return notation
        'no_useless_return' => true,
        'return_assignment' => true,
        'simplified_null_return' => true,

        // Semicolon
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
        'no_empty_statement' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'semicolon_after_instruction' => true,

        // String notation
        'explicit_string_variable' => true,
        'heredoc_to_nowdoc' => true,
        'simple_to_complex_string_variable' => true,
        'single_quote' => ['strings_containing_single_quote_chars' => false],

        // Whitespace
        'array_indentation' => true,
        'compact_nullable_typehint' => true,
        'heredoc_indentation' => true,
        'method_chaining_indentation' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'break',
                'continue',
                'extra',
                'return',
                'throw',
                'use',
                'parenthesis_brace_block',
                'square_brace_block',
                'curly_brace_block',
            ],
        ],
        'no_spaces_around_offset' => true,
        'no_whitespace_in_blank_line' => true,
        'single_line_empty_body' => false,

        // WordPress specific adjustments
        'braces' => [
            'allow_single_line_closure' => true,
            'position_after_functions_and_oop_constructs' => 'next',
        ],

        // Disable some rules that conflict with WordPress coding standards
        'single_line_throw' => false,
        'phpdoc_to_comment' => false, // Keep PHPDoc blocks as is
        'no_superfluous_phpdoc_tags' => false, // WordPress uses @param even when typed
    ])
    ->setFinder($finder);
