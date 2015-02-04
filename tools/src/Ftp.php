<?php

namespace Brunodebarros\Gitdeploy;

use Brunodebarros\Gitdeploy\Helpers;

class Ftp extends Server {

    public function connect($test = false) {
        if (!extension_loaded('ftp')) {
            Helpers::error("You need the FTP extension to be enabled if you want to deploy via FTP.");
        }

        if (!$this->connection or $test) {
            $server = $this->server;
            $this->connection = @ftp_connect($server['host'], $server['port'], 30);

            if (!$this->connection) {
                Helpers::error("Could not connect to {$this->host}");
            } else {
                if (!@ftp_login($this->connection, $server['user'], $server['pass'])) {
                    Helpers::error("Could not login to {$this->host}");
                }

                ftp_pasv($this->connection, $server['passive']);

                if (!ftp_chdir($this->connection, $server['path'])) {
                    Helpers::error("Could not change the directory to {$server['path']} on {$this->host}");
                }
            }

            Helpers::logmessage("Connected to: {$this->host}");
            $this->current_commit = $this->get_file('REVISION', true);
        }

        if ($test) {
            $this->disconnect();
        }
    }

    public function disconnect() {
        ftp_close($this->connection);
        $this->connection = null;
        Helpers::logmessage("Disconnected from: {$this->host}");
    }

    public function get_file($file, $ignore_if_error = false) {
        $this->connect();

        $tmpFile = tempnam(sys_get_temp_dir(), 'GITDEPLOYPHP');

        if ($ignore_if_error) {
            $result = @ftp_get($this->connection, $tmpFile, $file, FTP_BINARY);
        } else {
            # Display whatever error PHP throws.
            $result = ftp_get($this->connection, $tmpFile, $file, FTP_BINARY);
        }

        if ($result) {
            return file_get_contents($tmpFile);
        } else {
            # Couldn't get the file. I assume it's because the file didn't exist.
            if ($ignore_if_error) {
                return false;
            } else {
                Helpers::error("Failed to retrieve '$file'.");
            }
        }
    }

    public function set_file($file, $contents, $die_if_fail = false) {
        $this->connect();

        # Make sure the folder exists in the FTP server.

        $dir = explode("/", dirname($file));
        $dir_part_count = count($dir);
        $path = "";

        for ($i = 0; $i < $dir_part_count; $i++) {
            $path.= $dir[$i] . '/';

            if (!isset($this->existing_paths_cache[$path])) {
                $origin = ftp_pwd($this->connection);

                if (!@ftp_chdir($this->connection, $path)) {
                    if (!@ftp_mkdir($this->connection, $path)) {
                        Helpers::error("Failed to create the directory '$path'. Upload to this server cannot continue.");
                    } else {
                        Helpers::logmessage("Created directory: $path");
                        $this->existing_paths_cache[$path] = true;
                    }
                } else {
                    $this->existing_paths_cache[$path] = true;
                }

                ftp_chdir($this->connection, $origin);
            }
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'GITDEPLOYPHP');
        file_put_contents($tmpFile, $contents);
        $uploaded = ftp_put($this->connection, $file, $tmpFile, FTP_BINARY);

        if (!$uploaded) {
            if ($die_if_fail) {
                Helpers::error("Failed to upload {$file}. Deployment will stop to allow you to check what went wrong.");
            } else {
                # Try deleting the file and reuploading.
                # This resolves a CHMOD issue with some FTP servers.
                $this->unset_file($file);
                $this->set_file($file, $contents, true);
            }
        } else {
            Helpers::logmessage("Uploaded: $file");
            return true;
        }
    }

    protected function recursive_remove($file_or_directory, $die_if_fail = false) {
        $this->connect();

        if (!(@ftp_rmdir($this->connection, $file_or_directory) || @ftp_delete($this->connection, $file_or_directory))) {

            if ($die_if_fail) {
                return false;
            }

            $filelist = ftp_nlist($this->connection, $file_or_directory);

            foreach ($filelist as $file) {
                if ($file != '.' && $file != '..') {
                    $this->recursive_remove($file);
                }
            }

            $this->recursive_remove($file_or_directory, true);
        }
    }

    public function mkdir($file) {
        $this->connect();

        ftp_mkdir($this->connection, $file);
        Helpers::logmessage("Created directory: $file");
    }

    public function unset_file($file) {
        $this->connect();

        $this->recursive_remove($file);
        Helpers::logmessage("Deleted: $file");
    }

}
