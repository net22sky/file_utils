<?php

namespace App;

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use App\Services\Loggers ;
use Dotenv\Dotenv;
use App\Models\Database;

// Инициализируем логгер
$logFile = __DIR__ . '/logs/app.log';
$logger = new Loggers($logFile);

// Загружаем переменные окружения из .env файла
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $envVariables = parse_ini_file($envFile);
    if ($envVariables === false) {
        die("Error: Unable to parse .env file.\n");
    }
    foreach ($envVariables as $key => $value) {
        putenv("$key=$value");
        print_r($key);
    }
} else {
    die("Error: .env file not found.\n");
}

// Настройка параметров подключения к базе данных
$dbConfig = [
    'driver' => getenv('DB_CONNECTION'),
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT'),
    'database' => getenv('DB_DATABASE'),
    'username' => getenv('DB_USERNAME'),
    'password' => getenv('DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
];

// Проверка подключения к базе данных через PDO
function connectToDatabase(array $dbConfig, Loggers $logger): bool {
    $maxAttempts = intval(getenv('DB_MAX_ATTEMPTS') ?? 5); // Максимальное количество попыток
    $attemptDelay = intval(getenv('DB_ATTEMPT_DELAY') ?? 2); // Задержка между попытками (в секундах)
    echo "Подключение 1";
    print_r($dbConfig);
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        echo "Подключение";
        try {

            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8";
                $options = [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ];

                $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);

            // Создаём новое соединение через PDO
            /*$dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ];

            new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);*/

            $logger->info("Подключение к базе данных успешно установлено.", 'info');
            echo "Подключение к базе данных успешно установлено.\n";
            return true;
        } catch (\PDOException $e) {
            $message = "Ошибка подключения к базе данных (попытка #$attempt): " . $e->getMessage();
            $logger->error($message, 'error');
            echo $message . "\n";

            if ($attempt === $maxAttempts) {
                return false;
            }

            echo "Повторная попытка через {$attemptDelay} секунд...\n";
            sleep($attemptDelay);
        }
    }

    return false;
}

// Выполняем проверку подключения
if (!connectToDatabase($dbConfig ,$logger)) {
    $logger->error("Не удалось подключиться к базе данных после всех попыток.", 'critical');
    exit(1);
}
/*
// Инициализация Eloquent
$capsule = new Capsule;

// Передаём конфигурацию Eloquent
$capsule->addConnection([
    'driver' => getenv('DB_CONNECTION'),
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT'),
    'database' => getenv('DB_DATABASE'),
    'username' => getenv('DB_USERNAME'),
    'password' => getenv('DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

// Запуск Eloquent
$capsule->setAsGlobal();
$capsule->bootEloquent();
*/

// initialize Illuminate database connection 
new Database();