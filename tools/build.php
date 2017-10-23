#!/usr/bin/env php
<?php

class NonProjectFilesFilter extends RecursiveFilterIterator {
    public function accept() {
        if ($this->hasChildren()) {
            return true;
        } else {
            /** @var SplFileInfo $current */
            $current = $this->current();
            $realpath = substr($current->getRealPath(), strlen(dirname(__FILE__) . DIRECTORY_SEPARATOR));
            $extension = $current->getExtension();
            $valid_prefixes = ["src/", "vendor/composer/", "vendor/league/", "vendor/phpseclib/", "vendor/seld/", "vendor/myclabs/"];

            if ($realpath == "vendor/autoload.php") {
                return true;
            }

            foreach ($valid_prefixes as $prefix) {
                if (substr($realpath, 0, strlen($prefix)) == $prefix && $extension == "php") {
                    return true;
                }
            }

            return false;
        }
    }
}

if (ini_get("phar.readonly")) {
    echo "You need to set the 'phar.readonly' option to 'Off' in your php.ini file (" . php_ini_loaded_file() . ")" . PHP_EOL;
} else {
    $phar = new Phar('git-deploy.phar', 0, 'git-deploy');
    $iterator = new NonProjectFilesFilter(new RecursiveDirectoryIterator(dirname(__FILE__), FilesystemIterator::SKIP_DOTS));
    $d = $phar->buildFromIterator(new RecursiveIteratorIterator($iterator), dirname(__FILE__));
    $phar->setStub(file_get_contents(dirname(__FILE__) . "/index.php"));
    unset($phar);
    $path_to_git_deploy = dirname(__FILE__) . "/../git-deploy";
    @unlink($path_to_git_deploy);
    rename('git-deploy.phar', $path_to_git_deploy);
    chmod($path_to_git_deploy, 0755);
    echo "Built git-deploy successfully!" . PHP_EOL;
}
