<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Database.php'; // Подключение базы данных
require_once __DIR__ . '/App/Config/Config.php'; // Конфигурация

use App\Core\DocumentProcessor;
use App\Config;
use App\Services\Loggers as Logger;

// Инициализация логгера
$logFile = __DIR__ . '/logs/app.log';
$logger = new Logger($logFile);

try {
    // Проверка наличия необходимых модулей PHP
    $requiredExtensions = [
        'pdo_mysql', // Для работы с MySQL (Eloquent использует PDO)
        'simplexml', // Для работы с FB2 (XML-файлы)
        'imagick',   // Для создания миниатюр (ImageMagick)
        'gd',        // Альтернатива для создания миниатюр (если нет ImageMagick)
    ];

    function checkPhpExtensions(array $requiredExtensions, Logger $logger): bool {
        $missingExtensions = [];
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $missingExtensions[] = $extension;
            }
        }

        if (!empty($missingExtensions)) {
            $logger->critical("Необходимы следующие PHP-модули:\n" . implode("\n", $missingExtensions));
            echo "Ошибка: Необходимы следующие PHP-модули:\n";
            foreach ($missingExtensions as $extension) {
                echo "- $extension\n";
            }
            return false;
        }

        $logger->info("Все необходимые PHP-модули загружены.");
        return true;
    }

    if (!checkPhpExtensions($requiredExtensions, $logger)) {
        exit(1); // Прерываем выполнение скрипта, если модули отсутствуют
    }

    // Чтение конфигурации
    $configPath = "config.json";
    $config = Config\readConfig($configPath);

    // Обработка документов
    $logger->info("Начало обработки документов...");
    $processor = new DocumentProcessor($config, $logger);
    $results = $processor->processDirectory();

    if (!empty($results)) {
        $processor->printResults($results);
    } else {
        echo "Документы не найдены.\n";
    }

    $logger->info("Обработка документов завершена.");
    echo "Готово! Данные сохранены в базу данных.\n";

} catch (\Exception $e) {
    $logger->critical("Произошла ошибка: " . $e->getMessage());
    echo "Произошла ошибка: " . $e->getMessage() . "\n";
    exit(1); // Прерываем выполнение при возникновении ошибки
}