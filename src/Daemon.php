<?php

namespace oNeDaL;


class Daemon
{
    /**
     * Daemonize a callback
     *
     * @throws \Exception
     *
     * @param array $options Set of options
     * @param callable $callable Callback to daemonize
     *
     * @return bool True on success, throws an Exception otherwise
     */
    public static function work(array $options, callable $callable)
    {

        if (!isset($options['pid'])) {
            throw new \Exception('pid not specified');
        }

        $options = $options + array(
            'stdin'  => '/dev/null',
            'stdout' => '/dev/null',
            'stderr' => 'php://stdout',
        );

        if (($lock = @fopen($options['pid'], 'c+')) === false) {
            throw new \Exception('unable to open pid file ' . $options['pid']);
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            throw new \Exception('could not acquire lock for ' . $options['pid']);
        }

        switch ($pid = pcntl_fork()) {
            case -1:
                throw new \Exception('unable to fork');
            case 0:
                break;
            default:
                fseek($lock, 0);
                ftruncate($lock, 0);
                fwrite($lock, $pid);
                fflush($lock);
                return;
        }

        if (posix_setsid() === -1) {
            throw new \Exception('failed to setsid');
        }

        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);

        if (!($stdin  = fopen($options['stdin'], 'r'))) {
            throw new \Exception('failed to open STDIN ' . $options['stdin']);
        }

        if (!($stdout = fopen($options['stdout'], 'w'))) {
            throw new \Exception('failed to open STDOUT ' . $options['stdout']);
        }

        if (!($stderr = fopen($options['stderr'], 'w'))) {
            throw new \Exception('failed to open STDERR ' . $options['stderr']);
        }

        pcntl_signal(SIGTSTP, SIG_IGN);
        pcntl_signal(SIGTTOU, SIG_IGN);
        pcntl_signal(SIGTTIN, SIG_IGN);
        pcntl_signal(SIGHUP,  SIG_IGN);

        call_user_func($callable, $stdin, $stdout, $stderr);
    }

    /**
     * Checks whether a daemon process specified by its PID file is running.
     *
     * @throws \Exception
     *
     * @param string $file   Daemon PID file
     *
     * @return bool True if the daemon is still running, false otherwise
     */
    public static function isRunning($file)
    {
        if (!is_readable($file)) {
            return false;
        }

        if (($lock = @fopen($file, 'c+')) === false) {
            throw new \Exception('unable to open pid file ' . $file);
        }

        if (flock($lock, LOCK_EX | LOCK_NB)) {
            return false;
        } else {
            flock($lock, LOCK_UN);
            return true;
        }
    }

    /**
     * Kills a daemon process specified by its PID file.
     *
     * @throws \Exception
     *
     * @param string $file   Daemon PID file
     * @param bool   $delete Flag to delete PID file after killing
     * @param integer $signal Signal
     *
     * @link http://php.net/manual/en/pcntl.constants.php PCNTL signals
     * @return bool TRUE on success, FALSE otherwise
     */
    public static function kill($file, $delete = false, $signal = SIGTERM)
    {
        if (!is_readable($file)) {
            throw new \Exception('unreadable pid file ' . $file);
        }

        if (($lock = @fopen($file, 'c+')) === false) {
            throw new \Exception('unable to open pid file ' . $file);
        }

        if (flock($lock, LOCK_EX | LOCK_NB)) {
            flock($lock, LOCK_UN);
            throw new \Exception('process not running');
        }

        $pid = fgets($lock);

        if (posix_kill($pid, $signal)) {
            if ($delete) {
                unlink($file);
            }
            return true;
        } else {
            return false;
        }
    }

}
