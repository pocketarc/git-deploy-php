<?php

namespace Brunodebarros\Gitdeploy;

class Helpers {

    public static function logmessage($message) {
        static $log_handle = null;

        $log = "[" . @date("Y-m-d H:i:s O") . "] " . $message . PHP_EOL;
        if (defined("WRITE_TO_LOG")) {
            if ($log_handle === null) {
                $log_handle = fopen(WRITE_TO_LOG, 'a');
            }

            fwrite($log_handle, $log);
        }

        echo $log;
    }

    public static function error($message) {
        self::logmessage("ERROR: $message");
        die;
    }

    public static function get_recursive_file_list($folder, $prefix = '') {

        # Add trailing slash
        $folder = (substr($folder, strlen($folder) - 1, 1) == '/') ? $folder : $folder . '/';

        $return = array();

        foreach (self::clean_scandir($folder) as $file) {
            if (is_dir($folder . $file)) {
                $return = array_merge($return, self::get_recursive_file_list($folder . $file, $prefix . $file . '/'));
            } else {
                $return[] = $prefix . $file;
            }
        }

        return $return;
    }

    public static function clean_scandir($folder, $ignore = array()) {
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

}
