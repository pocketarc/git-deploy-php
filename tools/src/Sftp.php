<?php

namespace Brunodebarros\Gitdeploy;

use Brunodebarros\Gitdeploy\Helpers;

class Sftp extends Server {

    public function connect($test = false) {
        if (!$this->connection or $test) {
            $server = $this->server;
            require_once('Crypt/RSA.php');
            require_once('Net/SFTP.php');

            $this->connection = new \Net_SFTP($server['host'], $server['port'], 10);
            $logged_in = false;

            if (isset($server['sftp_key'])) {
                $key = new \Crypt_RSA();
                if (isset($server['pass']) && ! empty($server['pass'])) {
                    $key->setPassword($server['pass']);
                }
                $key->loadKey(file_get_contents($server['sftp_key']));
                $logged_in = $this->connection->login($server['user'], $key);

                if (!$logged_in) {
                    Helpers::error("Could not login to {$this->host}. It may be because the key requires a passphrase, which you need to specify it as the 'pass' attribute.");
                }
            } else {
                $logged_in = $this->connection->login($server['user'], $server['pass']);

                if (!$logged_in) {
                    Helpers::error("Could not login to {$this->host}");
                }
            }

            if (!$this->connection->chdir($server['path'])) {
                Helpers::error("Could not change the directory to {$server['path']} on {$this->host}");
            }

            Helpers::logmessage("Connected to: {$this->host}");
            $this->current_commit = $this->get_file('REVISION', true);
        }

        if ($test) {
            $this->disconnect();
        }
    }

    public function disconnect() {
        $this->connection->disconnect();
        $this->connection = null;
        Helpers::logmessage("Disconnected from: {$this->host}");
    }

    public function get_file($file, $ignore_if_error = false) {
        $this->connect();

        $contents = $this->connection->get($file);
        if ($contents) {
            return $contents;
        } else {
            # Couldn't get the file. I assume it's because the file didn't exist.
            if ($ignore_if_error) {
                return false;
            } else {
                Helpers::error("Failed to retrieve '$file'.");
            }
        }
    }

    public function set_file($file, $contents) {
        $this->connect();

        $file = $file;
        $dir = explode("/", dirname($file));
        $dir_part_count = count($dir);
        $path = "";

        for ($i = 0; $i < $dir_part_count; $i++) {
            $path.= $dir[$i] . '/';

            if (!isset($this->existing_paths_cache[$path])) {
                $origin = $this->connection->pwd();

                if (!$this->connection->chdir($path)) {
                    if (!$this->connection->mkdir($path)) {
                        Helpers::error("Failed to create the directory '$path'. Upload to this server cannot continue.");
                    } else {
                        Helpers::logmessage("Created directory: $path");
                        $this->existing_paths_cache[$path] = true;
                    }
                } else {
                    $this->existing_paths_cache[$path] = true;
                }

                $this->connection->chdir($origin);
            }
        }

        if ($this->connection->put($file, $contents)) {
            Helpers::logmessage("Uploaded: $file");
            return true;
        } else {
            Helpers::error("Failed to upload {$file}. Deployment will stop to allow you to check what went wrong.");
        }
    }

    protected function recursive_remove($file_or_directory, $if_dir = false) {
        $this->connect();

        $parent = dirname($file_or_directory);
        if ($this->connection->delete($file_or_directory, $if_dir)) {
            $filelist = $this->connection->nlist($parent);
            foreach ($filelist as $file) {
                if ($file != '.' && $file != '..') {
                    return false;
                }
            }

            $this->recursive_remove($parent, true);
        }
    }

    public function mkdir($file) {
        $this->connect();

        $this->connection->mkdir($file);
        Helpers::logmessage("Created directory: $file");
    }

    public function unset_file($file) {
        $this->connect();

        $this->recursive_remove($file, false);
        Helpers::logmessage("Deleted: $file");
    }

}
