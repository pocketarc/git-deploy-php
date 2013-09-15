<?php

function get_recursive_file_list($folder, $prefix = '') {

    # Add trailing slash
    $folder = (substr($folder, strlen($folder) - 1, 1) == '/') ? $folder : $folder . '/';

    $return = array();

    foreach (clean_scandir($folder) as $file) {
        if (is_dir($folder . $file)) {
            $return = array_merge($return, get_recursive_file_list($folder . $file, $prefix . $file . '/'));
        } else {
            $return[] = $prefix . $file;
        }
    }

    return $return;
}

function clean_scandir($folder, $ignore = array()) {
    $ignore[] = '.';
    $ignore[] = '..';
    $ignore[] = '.DS_Store';
    $return = array();

    foreach (scandir($folder) as $file) {
        if (!in_array($file, $ignore)) {
            $return[] = $file;
        }
    }

    return $return;
}

$phpseclib = dirname(__FILE__).'/phpseclib/';
$opensslcnf = var_export(file_get_contents($phpseclib.'openssl.cnf'), true);
$packaged = "";

foreach (get_recursive_file_list($phpseclib) as $file) {
    if (stristr($file, '.php') !== false) {
        $contents = file_get_contents($phpseclib.$file);
        $contents = str_ireplace('<?php', '', $contents);
        $contents = str_ireplace("define('CRYPT_RSA_OPENSSL_CONFIG', dirname(__FILE__) . '/../openssl.cnf');", <<<update
\$tmpFile = tempnam(sys_get_temp_dir(), 'GITDEPLOYPHP');
file_put_contents(\$tmpFile, $opensslcnf);
define('CRYPT_RSA_OPENSSL_CONFIG', \$tmpFile);
update
, $contents);
        
        $packaged .= $contents;
    }
}

file_put_contents(dirname(__FILE__).'/packaged.php', $packaged);