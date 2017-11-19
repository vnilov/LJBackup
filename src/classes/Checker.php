<?php

class Checker
{
    const PROC_FILE = "runner.proc";

    public static function check()
    {
        if (file_exists("config.json")) {
            // get last pid from file
            $pid = file_get_contents(dirname(__FILE__) . '/../' . self::PROC_FILE);
            ob_start();
            // get current process list
            system("ps -e | awk '{print $1}'");
            // set it to an array
            $system = ob_get_clean();
            $pids = explode(PHP_EOL, $system);
            // if there is a live previous process, than do nothing
            if (in_array($pid, $pids)) {
                return false;
            }
        }
        file_put_contents(dirname(__FILE__) . '/' . self::PROC_FILE, getmypid());
        return true;
    }
}