<?php
/**
 * Created by PhpStorm.
 * User: weizeng
 * Date: 2018/3/16
 * Time: 下午3:54
 */

require "../vendor/autoload.php";

use EagleEye\Classes\Log;

Log::write("this is debug message,direct write to [date >] log file",'../log/fend/fend.log',1);
Log::rawwrite("this is debug message,direct write to log file",'../log/fend/fend.log',1);

Log::setLogLevel(Log::LOG_TYPE_DEBUG);
Log::debug("EagleEye","eagle eye log -- debug",__FILE__,__LINE__);
Log::trace("EagleEye","eagle eye log -- trace");
Log::notice("EagleEye","eagle eye log -- notice",__FILE__,__LINE__);
Log::info("EagleEye","eagle eye log -- info");
Log::error("EagleEye","eagle eye log -- error",__FILE__,__LINE__);
Log::alarm("EagleEye","eagle eye log -- alarm");
Log::exception("EagleEye","eagle eye log -- exception");


