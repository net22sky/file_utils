<?php

namespace App\Services;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Loggers {
    private $logger;

    public function __construct(string $logFile) {
        // Создаём новый экземпляр логгера
        $this->logger = new Logger('document_processor');

        // Добавляем обработчик для записи логов в файл
        $this->logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));
    }

    public function info(string $message): void {
        $this->logger->info($message);
    }

    public function error(string $message): void {
        $this->logger->error($message);
    }

    public function warning(string $message): void {
        $this->logger->warning($message);
    }


    public function critical(string $message): void {
        $this->logger->critical($message);
    }
    
}