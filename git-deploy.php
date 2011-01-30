<?php

if (isset($argv[1])) {
    $deploy = $argv[1];
} else {
    $deploy = 'deploy.ini';
}

if (!@file_exists($deploy)) {
    throw new Exception("File '$deploy' does not exist.");
} else {
    $servers = parse_ini_file($deploy, true);
    if (!$servers) {
        throw new Exception("File '$deploy' is not a valid .ini file.");
    }
}

foreach ($servers as $uri => $options) {

    if (substr($uri, 0, 6) === 'ftp://') {
        $options = array_merge($options, parse_url($uri));
    }

    # Throw in some default values, in case they're not set.
    $options = array_merge(array(
                'skip' => false,
                'host' => '',
                'user' => '',
                'pass' => '',
                'port' => 21,
                'path' => '/',
                'passive' => true
                    ), $options);

    if ($options['skip']) {
        continue;
    }

    deployGitOverFtp($options['host'], $options['user'], $options['pass'], $options['port'], $options['path'], $options['passive']);
}

function deployGitOverFtp($host, $user = '', $pass = '', $port = 21, $path = '/', $passive = true) {

    # Let's make sure the $path ends with a slash.

    if (substr($path, strlen($path) - 1, strlen($path)) !== '/') {
        $path = $path . '/';
    }

    # Okay, let's connect to the server.

    $connection = @ftp_connect($host, $port);

    if (!$connection) {
        throw new Exception("Could not connect to $host.");
    } else {

        if (!@ftp_login($connection, $user, $pass)) {
            throw new Exception("Could not login to $host (Tried to login as $user).");
        }


        ftp_pasv($connection, $passive);

        if (ftp_chdir($connection, $path)) {

        } else {
            throw new Exception("Could not change the FTP directory to $path.");
        }

        echo "----------------------------\r\n";
        echo "Connected to {$host}{$path}\r\n";
        echo "----------------------------\r\n";

        # Now that we're logged in to the server, let's get the remote revision.

        $remoteRevision = '';

        $tmpFile = tmpfile();

        if (@ftp_fget($connection, $tmpFile, 'REVISION', FTP_ASCII)) {
            fseek($tmpFile, 0);
            $remoteRevision = trim(fread($tmpFile, 1024));
            fclose($tmpFile);
        } else {
            # Couldn't get the file. I assume it's because the file didn't exist.
        }

        # Get the list of files to update.

        if (!empty($remoteRevision)) {
            $command = "git diff --name-status {$remoteRevision}...HEAD";
        } else {
            $command = "git ls-files";
        }

        $output = array();
        exec($command, $output);

        $filesToUpload = array();
        $filesToDelete = array();

        if (!empty($remoteRevision)) {
            foreach ($output as $line) {
                if ($line[0] == 'A' or $line[0] == 'C' or $line[0] == 'M') {
                    $filesToUpload[] = trim(substr($line, 1, strlen($line)));
                } elseif ($line[0] == 'D') {
                    $filesToDelete[] = trim(substr($line, 1, strlen($line)));
                } else {
                    throw new Exception("Unknown git-diff status: {$line[0]}");
                }
            }
        } else {
            $filesToUpload = $output;
        }

        foreach ($filesToUpload as $file) {
            # Make sure the folder exists in the FTP server.

            $origin = ftp_pwd($connection);
            $dir = dirname($file);

            if (!@ftp_chdir($connection, $dir)) {
                ftp_mkdir($connection, $dir);
            }

            ftp_chdir($connection, $origin);

            ftp_put($connection, $file, $file, FTP_BINARY);
            echo "Uploaded {$file}\r\n";
        }

        foreach ($filesToDelete as $file) {
            ftp_delete($connection, $file);
            echo "Deleted {$file}\r\n";
        }

        $temp = tempnam(sys_get_temp_dir(), 'gitRevision');
        file_put_contents($temp, exec('git rev-parse HEAD'));
        ftp_put($connection, 'REVISION', $temp, FTP_BINARY);
        unlink($temp);
        echo "Uploaded REVISION file\r\n";

        echo "Finished working on {$host}{$path}\r\n";

        ftp_close($connection);
    }
}