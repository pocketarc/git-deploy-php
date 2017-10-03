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
            $command = "diff --no-renames --name-status {$current_commit} {$target_commit}";
        } else {
            $command = "ls-files";
        }

        $return = array(
            'upload' => array(),
            'delete' => array(),
            'submodules' => $submodule_paths,
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
                } elseif ($line[0] == 'R') {
                    $details = preg_split("/\\s+/", $line);
                    $return['delete'][] = $details[1];
                    $return['upload'][] = $details[2];
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

    // http://stackoverflow.com/a/3278427
    public function get_status_towards_remote($local_branch, $remote_branch)
    {
        if (empty($local_branch))
        {
            $local_branch = "@";
        }

        if (empty($remote_branch))
        {
            $remote_branch = "@{u}";
        }

        $status;

        $this->exec("remote update");

        $local = $this->exec("rev-parse ".$local_branch);
        $remote = $this->exec("rev-parse ".$remote_branch);
        $base = $this->exec("merge-base ".$local_branch." ".$remote_branch);

        if ($local == $remote)
        {
            $status = "up-to-date";
        }
        else if ($local == $base)
        {
            $status = "pull-needed";
        }
        else if ($remote == $base)
        {
            $status = "push-needed";
        }
        else
        {
            $status = "diverged";
        }

        return $status;
    }

    protected function get_file_contents($path) {
        $temp = tempnam(sys_get_temp_dir(), "git-deploy-");
        $this->exec('show "' . $path . '"', "> \"$temp\"");
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
