#!/usr/bin/env php
<?php

ini_set('memory_limit', '-1');
Phar::mapPhar("git-deploy");

/**
 * PSR-4 autoloader.
 *      
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register(function ($class) {

    // project-specific namespace prefix
    $prefix = 'Brunodebarros\\Gitdeploy\\';

    // base directory for the namespace prefix
    $base_dir = 'phar://git-deploy/src/';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

set_include_path(get_include_path() . PATH_SEPARATOR . 'phar://git-deploy/phpseclib0.3.9/');

$args = \Brunodebarros\Gitdeploy\Config::getArgs();
$servers = \Brunodebarros\Gitdeploy\Config::getServers($args['config_file']);
$git = new \Brunodebarros\Gitdeploy\Git($args['repo_path']);

foreach ($servers as $server) {
    if ($args['revert']) {
        $server->revert($git, $args['list_only']);
    } else {
        $server->deploy($git, $git->interpret_target_commit($args['target_commit'], $server->server['branch']), false, $args['list_only']);
    }
}

__HALT_COMPILER();
