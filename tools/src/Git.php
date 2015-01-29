<?php

namespace Brunodebarros\Gitdeploy;
use Brunodebarros\Gitdeploy\Helpers;

class Git {

    public static $git_executable_path = "git";
    public $repo_path;

    public function __construct($repo_path) {
        $this->repo_path = rtrim($repo_path, '/') . '/';

        # Test if has Git in cmd.
        if (stristr($this->exec("--version"), "git version") === false) {
            Helpers::error("The command '" . self::$git_executable_path . "' was not found.");
        }
    }

    public function interpret_target_commit($target_commit, $branch = null) {
        if ($branch !== null) {
            if ($target_commit == "HEAD") {
                # Get the HEAD commit of the branch specified in the deploy.ini
                $target_commit = $branch;
            }
        }

        return $this->exec("rev-parse $target_commit");
    }

    public function get_changes($target_commit, $current_commit) {

        if (file_exists(".gitmodules")) {
            $submodules = parse_ini_file(".gitmodules", true);
        } else {
            $submodules = array();
        }
        $submodule_paths = array();

        foreach ($submodules as $submodule) {
            $submodule_paths[] = $submodule['path'];
        }

        if (!empty($current_commit)) {
            $command = "diff --name-status {$current_commit} {$target_commit}";
        } else {
            $command = "ls-files";
        }

        $return = array(
            'upload' => array(),
            'delete' => array(),
            'submodules' => $submodule_paths
        );

        $command = str_replace(array("\n", "\r\n"), '', $command);
        $result = $this->exec($command);

        if (empty($result)) {
            # Nothing has changed.
            return $return;
        }

        $result = explode("\n", $result);

        if (!empty($current_commit)) {
            foreach ($result as $line) {
                if ($line[0] == 'A' or $line[0] == 'C' or $line[0] == 'M') {
                    $path = trim(substr($line, 1, strlen($line)));
                    $return['upload'][$path] = $this->get_file_contents("$target_commit:\"$path\"");
                } elseif ($line[0] == 'D') {
                    $return['delete'][] = trim(substr($line, 1, strlen($line)));
                } else {
                    Helpers::error("Unknown git-diff status: {$line[0]}");
                }
            }
        } else {
            foreach ($result as $file) {
                if (!in_array($file, $submodule_paths)) {
                    $return['upload'][$file] = $this->get_file_contents("$target_commit:$file");
                }
            }
        }

        return $return;
    }

    protected function get_file_contents($path) {
        $temp = tempnam(sys_get_temp_dir(), "git-deploy-");
        $this->exec("show $path", "> \"$temp\"");
        return file_get_contents($temp);
    }

    protected function exec($command, $suffix = "") {
        if (chdir($this->repo_path)) {
            $console = trim(shell_exec(self::$git_executable_path . " " . $command . " 2>&1 " . $suffix));
            return $console;
        } else {
            Helpers::error("Unable to access the git repository's folder.");
        }
    }

}
