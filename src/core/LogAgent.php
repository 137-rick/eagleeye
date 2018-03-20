<?php

/**
 * Class LogAgent
 * 日志统一dump类
 */

namespace EagleEye\Core;

class LogAgent
{

    //最大 mode = 2 时，dump 日志阀值，当暂存日志超过这个数量后马上开始 dump
    const MAX_LOG_DUMP_COUNT = 20;

    static $isinit = 0;

    static $dumplogmode = 0; //日志落地模式 0 直接写入文件。1 缓存定期写入文件。2 dump到channel 异步写入文件

    static $channel = null;

    static $logTempArray = array();

    public static $dumppath = "logs/eagleeye/";//default dump path

    /**
     * 日志初始化
     * @param string $logpath
     * @throws Exception 启动模式和运行模式不匹配时会抛异常
     */
    public static function setLogPath($dumpPath)
    {
        //log dump path
        self::$dumppath = $dumpPath;
    }

    /**
     * 更改日志dump 模式
     * @param $mode 0 直接写入文件，1 缓存定期dump 2  swoole下多进程 channel
     * @throws Exception
     */
    public static function setDumpLogMode($mode)
    {
        if (self::$dumplogmode == 0 && $mode >= 0 && $mode <= 3) {
            self::$dumplogmode = $mode;
        } else {
            return;
        }

        //buffer log
        if ($mode == 1) {
            register_shutdown_function(array("EagleEye_LogAgent", "memoryDumpLog"));
            return;
        }

        //async log dumper
        if ($mode == 2) {

            //logagent buffer
            if (self::$channel == null) {
                self::$channel = new \swoole_channel(256 * 1024 * 1024);
            }

            //not cli mode wrong
            if (php_sapi_name() != "cli") {
                echo "The LogAgent Mode 3 Only Run on Swoole Cli Mode..";

                throw new \Exception("The LogAgent Mode 3 Only Run on Swoole Cli Mode..", 11112);
            }
            return;
        }
    }

    /**
     * 获取日志落地队列状态
     * @return mixed
     */
    public static function getQueueStat()
    {
        //get queue stat
        return self::$channel->stats();
    }


    /**
     * 根据不同的日志记录模式
     * 0 直接写入模式
     * 1 内存缓存，溢满dump及shutdown时落地
     * 2 swoole模式，channel收集多进程日志，异步process落地
     * 目前这个设置在changeMode函数
     *
     * @param array $log
     * @throws Exception 日志工作模式错误会抛出异常
     */
    public static function log($log)
    {
        if (self::$dumplogmode == 0) {
            //direct dump log file
            file_put_contents(self::$dumppath . "/" . date("Y-m-d") . "_" . php_sapi_name() . ".log", json_encode($log) . "\n", FILE_APPEND);

        } else if (self::$dumplogmode == 1) {
            //dump to the memory
            self::$logTempArray[] = $log;

            if (count(self::$logTempArray) > self::MAX_LOG_DUMP_COUNT) {
                self::memoryDumpLog();
            }

        } else if (self::$dumplogmode == 2) {
            self::$channel->push($log);
        } else {
            echo "Log Agent不支持的日志落地模式！";
            throw new \Exception("不支持的日志落地模式！", 111111);
        }
    }

    /**
     * 通过内存暂存日志，在日志量大后或shutdown时将日志统一落地
     * 浪费内存，但是io少，可在fpm或cli内使用
     * 此函数建议注册在shutdown函数内
     */
    public static function memoryDumpLog()
    {
        $logStr = "";
        foreach (self::$logTempArray as $logItem) {
            $logStr .= (json_encode($logItem) . "\n");
        }
        file_put_contents(self::$dumppath . "/" . date("Y-m-d") . "_" . php_sapi_name() . ".log", $logStr, FILE_APPEND);
        self::$logTempArray = array();
    }

    /**
     * 通过Channel、异步落地日志文件
     * 适用swoole常驻多进程服务落地日志
     * 建议启动独立process运行此函数
     */
    public static function threadDumpLog()
    {

        //logagent buffer
        if (self::$channel == null) {
            echo "Logagent Dump Log must run befor change mode";
            throw new \Exception("Logagent Dump Log must run befor change mode", 11113);
        }

        //dump the log to the local
        $logcount = 0;
        $logstr = "";
        $startime = microtime(true);

        while (true) {
            $log = self::$channel->pop();
            //ok add the log
            if ($log !== false) {
                $log = json_encode($log);
                $logstr = $logstr . "\n" . $log;
                $logcount++;
            } else {
                sleep(1);
            }

            //logcount大于阀值 || 过去时间3秒 dump日志
            if ($logcount > self::MAX_LOG_DUMP_COUNT || microtime(true) - $startime > 3) {

                file_put_contents(self::$dumppath . "/" . date("Y-m-d") . "_swoole" . ".log", $logstr, FILE_APPEND);

                $logcount = 0;
                $logstr = "";
                $startime = microtime(true);
                continue;
            }
        }
    }

    /**
     * 一次性将 channel 中的日志全部 dump 到日志文件中
     * */
    public static function flushChannel()
    {
        if (self::$dumplogmode == 2) {
            $count = 0;
            $bulkContent = '';
            while ($log = self::$channel->pop()) {
                $bulkContent = $bulkContent . PHP_EOL . json_encode($log);
                $count++;
                if ($count > self::MAX_LOG_DUMP_COUNT) {
                    $count = 0;
                    file_put_contents(self::$dumppath . "/" . date("Y-m-d") . "_swoole" . ".log", $bulkContent, FILE_APPEND);
                    $bulkContent = '';
                    usleep(100);
                }
            }
            if (!empty($bulkContent)) {
                file_put_contents(self::$dumppath . "/" . date("Y-m-d") . "_swoole" . ".log", $bulkContent, FILE_APPEND);
            }
        }
    }


}