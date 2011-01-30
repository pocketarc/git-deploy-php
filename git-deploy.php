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

$revision = exec('git rev-parse HEAD');

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

        var_dump($output);

        ftp_close($connection);
    }
}