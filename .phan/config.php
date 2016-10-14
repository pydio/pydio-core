<?php

/**
 * This configuration will be read and overlaid on top of the
 * default configuration. Command line arguments will be applied
 * after this file is read.
 */
return [

    // Run a quick version of checks that takes less
    // time at the cost of not running as thorough
    // an analysis. You should consider setting this
    // to true only when you wish you had more issues
    // to fix in your code base.
    'quick_mode' => true,

    // If enabled, check all methods that override a
    // parent method to make sure its signature is
    // compatible with the parent's. This check
    // can add quite a bit of time to the analysis.
    'analyze_signature_compatibility' => true,

    // Backwards Compatibility Checking. This is slow
    // and expensive, but you should consider running
    // it before upgrading your version of PHP to a
    // new version that has backward compatibility
    // breaks.
    'backward_compatibility_checks' => true,

    // By default, Phan will not analyze all node types
    // in order to save time. If this config is set to true,
    // Phan will dig deeper into the AST tree and do an
    // analysis on all nodes, possibly finding more issues.
    'should_visit_all_nodes' => true,

    // If empty, no filter against issues types will be applied.
    // If this white-list is non-empty, only issues within the list
    // will be emitted by Phan.
    'whitelist_issue_types' => [
        'PhanCompatiblePHP7'
    ],

    // A list of directories that should be parsed for class and
    // method information. After excluding the directories
    // defined in exclude_analysis_directory_list, the remaining
    // files will be statically analyzed for errors.
    //
    // Thus, both first-party and third-party code being used by
    // your application should be included in this list.
    'directory_list' => [
        'core/src/core',
        'core/src/conf',
        'core/src/plugins'
    ],

    // A directory list that defines files that will be excluded
    // from static analysis, but whose class and method
    // information should be included.
    //
    // Generally, you'll want to include the directories for
    // third-party code (such as "vendor/") in this list.
    //
    // n.b.: If you'd like to parse but not analyze 3rd
    //       party code, directories containing that code
    //       should be added to the `directory_list` as
    //       to `excluce_analysis_directory_list`.
    "exclude_analysis_directory_list" => [
        'core/src/core/vendor/',
        'core/src/plugins/access.ajxp_conf/vendor/',
        'core/src/plugins/access.dropbox/vendor/',
        'core/src/plugins/access.webdav/vendor/',
        'core/src/plugins/action.scheduler/vendor/',
        'core/src/plugins/action.share/vendor/',
        'core/src/plugins/auth.ldapv2/vendor/',
        'core/src/plugins/cache.doctrine/vendor/',
        'core/src/plugins/core.access/vendor/',
        'core/src/plugins/core.mq/vendor/',
        'core/src/plugins/core.ocs/vendor/',
        'core/src/plugins/core.tasks/vendor/',
        'core/src/plugins/index.elasticsearch/vendor/',
        'core/src/plugins/meta.git/vendor/',
        'core/src/plugins/access.swift/openstack-sdk-php/'
    ],
];
