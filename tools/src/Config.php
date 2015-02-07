<?php

namespace Brunodebarros\Gitdeploy;
use Brunodebarros\Gitdeploy\Helpers;

class Config {

    public static function getArgs() {
        $argv = $_SERVER['argv'];

        $deploy = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'deploy.ini';
        $commands = array('-l', '-r', '-c', '-d', '--revert', '--log', '--repo');

        $deploy_file = isset($argv[1]) ? end($argv) : "deploy.ini";

        if (!in_array($deploy_file, $commands)) {
            $deploy = $deploy_file . (substr($deploy_file, -4) === '.ini' ? '' : '.ini');
        }

        $opts = getopt("lr:d:c:", array("revert", "log::", "repo:"));

        if (isset($opts['log'])) {
            define('WRITE_TO_LOG', $opts['revert'] ? $opts['revert'] : 'git_deploy_php_log.txt');
        }

        if (isset($opts['d'])) {
            $deploy = $opts['d'];
        }

        if (isset($opts['c'])) {
            $opts['r'] = $opts['c'];
        }

        if (isset($opts['repo'])) {
            $repo_path = $opts['repo'];
        } else {
            $repo_path = getcwd() . DIRECTORY_SEPARATOR;
        }

        return array(
            'config_file' => $deploy,
            'target_commit' => isset($opts['r']) ? $opts['r'] : 'HEAD',
            'list_only' => isset($opts['l']),
            'revert' => isset($opts['revert']),
            'repo_path' => $repo_path
        );
    }

    public static function getServers($config_file) {
        $servers = @parse_ini_file($config_file, true);
        $return = array();

        if (!$servers) {
            Helpers::error("File '$config_file' is not a valid .ini file.");
        } else {
            foreach ($servers as $uri => $options) {
                if (stristr($uri, "://") !== false) {
                    $options = array_merge($options, parse_url($uri));
                }

                # Throw in some default values, in case they're not set.
                $options = array_merge(array(
                    'skip' => false,
                    'scheme' => 'ftp',
                    'host' => '',
                    'user' => '',
                    'branch' => null,
                    'port' => 21,
                    'path' => '/',
                    'passive' => true,
                    'clean_directories' => array(),
                    'ignore_files' => array(),
                    'ignore_directories' => array(),
                    'upload_untracked' => array()
                        ), $options);

                if (!isset($options['pass']) && ! isset($options['sftp_key'])) {
                    $options['pass'] = self::promptPassword();
                }

                if (isset($options['sftp_key'])) {
                    if (substr($options['sftp_key'], 0, 2) == "~/") {
                        $options['sftp_key'] = $_SERVER['HOME'] . substr($options['sftp_key'], 1);
                    }
                }

                if ($options['skip']) {
                    continue;
                } else {
                    unset($options['skip']);
                    $type = "Brunodebarros\\Gitdeploy\\".ucfirst(strtolower($options['scheme']));
                    $return[$uri] = new $type($options, $config_file);
                }
            }
        }

        return $return;
    }

    public static function promptPassword() {
        $prompt = 'Enter ftp password: ';

        $command = "/usr/bin/env bash -c 'echo OK'";
        if (rtrim(shell_exec($command)) !== 'OK') {
            trigger_error("Can't invoke bash");
            return;
        }

        $command = "/usr/bin/env bash -c 'read -s -p \""
                . addslashes($prompt)
                . "\" mypassword && echo \$mypassword'";
        $password = rtrim(shell_exec($command));
        echo "\n";

        return $password;
    }

}
