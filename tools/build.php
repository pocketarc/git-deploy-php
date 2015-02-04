#!/usr/bin/env php
<?php

ini_set("phar.readonly", "Off");
$phar = new Phar('git-deploy.phar', 0, 'git-deploy');
$phar->buildFromDirectory(dirname(__FILE__));
$phar->setStub(file_get_contents(dirname(__FILE__)."/index.php"));
unset($phar);
@unlink('../git-deploy');
rename('git-deploy.phar', '../git-deploy');
