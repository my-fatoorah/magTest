<?php

namespace MyFatoorah\Gateway\Cron;

class BackupLogFiles {

    protected $log;

//---------------------------------------------------------------------------------------------------------------------------------------------------
    public function createNewLogFile() {

        $logPath = BP . '/var/log';

        if (file_exists(MYFATOORAH_LOG_FILE)) {

            $mfOldLog = "$logPath/mfOldLog";
            if (!file_exists($mfOldLog)) {
                mkdir($mfOldLog);
            }
            rename(MYFATOORAH_LOG_FILE, "$mfOldLog/myfatoorah_" . date('Y-m-d') . '.log');
        }
        return true;
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------
}
