#!/usr/bin/env php
<?php

if (ini_get("phar.readonly")) {
    echo "You need to set the 'phar.readonly' option to 'Off' in your php.ini file (" . php_ini_loaded_file() . ")".PHP_EOL;
} else {
    $phar = new Phar('git-deploy.phar', 0, 'git-deploy');
    $phar->buildFromDirectory(dirname(__FILE__));
    $phar->setStub(file_get_contents(dirname(__FILE__) . "/index.php"));
    unset($phar);
    @unlink('../git-deploy');
    rename('git-deploy.phar', '../git-deploy');
    echo "Built git-deploy successfully!".PHP_EOL;
}
