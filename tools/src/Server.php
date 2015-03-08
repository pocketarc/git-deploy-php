<?php

namespace Brunodebarros\Gitdeploy;
use Brunodebarros\Gitdeploy\Helpers;

abstract class Server {

    public $connection;
    public $current_commit;
    public $host;
    public $existing_paths_cache;
    public $clean_directories;
    public $ignore_files;
    public $ignore_directories;
    public $upload_untracked;
    public $server;

    public function __construct($server, $deploy_script = 'deploy.ini') {
        $this->server = $server;
        $this->clean_directories = $server['clean_directories'];
        $this->ignore_files = array_merge(array(
            '.gitignore', '.gitattributes', '.gitmodules', 'deploy.ini', 'git-deploy', $deploy_script
                ), $server['ignore_files']);
        $this->ignore_directories = $server['ignore_directories'];
        $this->upload_untracked = $server['upload_untracked'];
        $this->host = "{$server['scheme']}://{$server['user']}@{$server['host']}:{$server['port']}{$server['path']}";
        $this->connect(true);
    }

    public function deploy(Git $git, $target_commit, $is_revert = false, $list_only = false) {
        if ($target_commit == $this->current_commit) {
            Helpers::logmessage("Nothing to update on: $this->host");
            return;
        }

        if ($list_only) {
            Helpers::logmessage("DETECTED '-l'. NO FILES ARE BEING UPLOADED / DELETED, THEY ARE ONLY BEING LISTED.");
        }

        Helpers::logmessage("Started working on: {$this->host}");

        if ($is_revert) {
            Helpers::logmessage("Reverting server from " . substr($this->current_commit, 0, 6) . " to " . substr($target_commit, 0, 6) . "...");
        } elseif (empty($this->current_commit)) {
            Helpers::logmessage("Deploying to server for the first time...");
        } else {
            Helpers::logmessage("Updating server from " . substr($this->current_commit, 0, 6) . " to " . substr($target_commit, 0, 6) . "...");
        }

        # Get files between $commit and REVISION
        $changes = $git->get_changes($target_commit, $this->current_commit);

        foreach ($changes['upload'] as $file => $contents) {
            if (in_array($file, $this->ignore_files)) {
                unset($changes['upload'][$file]);
            }
            foreach ($this->ignore_directories as $ignoreDir) {
                if (strpos($file, $ignoreDir) !== false) {
                    unset($changes['upload'][$file]);
                    break;
                }
            }
        }

        foreach ($this->upload_untracked as $file) {
            if (file_exists($git->repo_path . $file)) {
                if (is_dir($git->repo_path . $file)) {
                    foreach (Helpers::get_recursive_file_list($git->repo_path . $file, $file . "/") as $buffer) {
                        $changes['upload'][$buffer] = file_get_contents($git->repo_path . $buffer);
                    }
                } else {
                    $changes['upload'][$file] = file_get_contents($git->repo_path . $file);
                }
            }
        }

        $submodule_meta = array();

        foreach ($changes['submodules'] as $submodule) {
            Helpers::logmessage($submodule);
            $current_subcommit = $this->get_file($submodule . '/REVISION', true);
            $subgit = new \Brunodebarros\Gitdeploy\Git($git->repo_path . $submodule . "/");
            $target_subcommit = $subgit->interpret_target_commit("HEAD");
            $subchanges = $subgit->get_changes($target_subcommit, $current_subcommit);

            $submodule_meta[$submodule] = array(
                'target_subcommit' => $target_subcommit,
                'current_subcommit' => $current_subcommit
            );

            foreach ($subchanges['upload'] as $file => $contents) {
                $changes['upload'][$submodule . "/" . $file] = $contents;
            }

            foreach ($subchanges['delete'] as $file => $contents) {
                $changes['delete'][$submodule . "/" . $file] = $contents;
            }
        }

        $count_upload = count($changes['upload']);
        $count_delete = count($changes['delete']);

        if ($count_upload == 0 && $count_delete == 0) {
            Helpers::logmessage("Nothing to update on: $this->host");
            return;
        }

        if ($count_upload > 0) {
            $count_upload = $count_upload + 2;
        }

        Helpers::logmessage("Will upload $count_upload file" . ($count_upload == 1 ? '' : 's') . ".");
        Helpers::logmessage("Will delete $count_delete file" . ($count_delete == 1 ? '' : 's') . ".");

        if (isset($this->server['maintenance_file'])) {
            $this->set_file($this->server['maintenance_file'], $this->server['maintenance_on_value']);
            Helpers::logmessage("Turned maintenance mode on.");
        }

        foreach ($changes['upload'] as $file => $contents) {
            if ($list_only) {
                Helpers::logmessage("Uploaded: $file");
            } else {
                if (!in_array($file, $changes['submodules'])) {
                    $this->set_file($file, $contents);
                }
            }
        }

        foreach ($changes['delete'] as $file) {
            if ($list_only) {
                Helpers::logmessage("Deleted: $file");
            } else {
                $this->unset_file($file);
            }
        }

        foreach ($this->clean_directories as $directory) {
            $this->unset_file($directory);
            $this->mkdir($directory);
        }

        foreach ($changes['submodules'] as $submodule) {
            $this->set_file($submodule . '/REVISION', $submodule_meta[$submodule]['target_subcommit']);
            $this->set_file($submodule . '/PREVIOUS_REVISION', (empty($submodule_meta[$submodule]['current_subcommit']) ? $submodule_meta[$submodule]['target_subcommit'] : $submodule_meta[$submodule]['current_subcommit']));
        }

        $this->set_current_commit($target_commit, $list_only);
    }

    public function revert($git, $list_only = false) {
        $target_commit = $this->get_file('PREVIOUS_REVISION', true);
        if (empty($target_commit)) {
            Helpers::error("Cannot revert: {$this->host} server has no PREVIOUS_REVISION file.");
        } else {
            $this->deploy($git, $target_commit, true, $list_only);
        }
    }

    protected function set_current_commit($target_commit, $list_only = false) {
        if (!$list_only) {
            $this->set_file('REVISION', $target_commit);
            $this->set_file('PREVIOUS_REVISION', (empty($this->current_commit) ? $target_commit : $this->current_commit));
        }

        if (isset($this->server['maintenance_file'])) {
            $this->set_file($this->server['maintenance_file'], $this->server['maintenance_off_value']);
            Helpers::logmessage("Turned maintenance mode off.");
        }

        Helpers::logmessage("Finished working on: {$this->host}");
        $this->disconnect();
    }

}
