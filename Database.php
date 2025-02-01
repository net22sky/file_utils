<?php
/*
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем переменные окружения из .env файла
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

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

// Проверка подключения к базе данных
function connectToDatabase(array $dbConfig): bool {
    $maxAttempts = 5; // Максимальное количество попыток подключения
    $attemptDelay = 2; // Задержка между попытками (в секундах)

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            // Создаём новое соединение через PDO
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
            $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            echo "Подключение к базе данных успешно установлено.\n";
            return true;
        } catch (\PDOException $e) {
            if ($attempt === $maxAttempts) {
                // Если это последняя попытка, выбрасываем ошибку
                echo "Ошибка подключения к базе данных (попытка #$attempt): " . $e->getMessage() . "\n";
                return false;
            }

            echo "Не удалось подключиться к базе данных (попытка #$attempt). Повторная попытка через {$attemptDelay} секунд...\n";
            sleep($attemptDelay);
        }
    }

    return false;
}

// Выполняем проверку подключения
if (!connectToDatabase($dbConfig)) {
    exit(1); // Прерываем выполнение скрипта, если подключение не удалось
}

// Инициализация Eloquent
$capsule = new Capsule;

$capsule->addConnection($dbConfig);

// Запуск Eloquent
$capsule->setAsGlobal();
$capsule->bootEloquent();

*/

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем переменные окружения из .env файла
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Настройка параметров подключения к базе данных
$dbConfig = [
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT'),
    'database' => getenv('DB_DATABASE'),
    'username' => getenv('DB_USERNAME'),
    'password' => getenv('DB_PASSWORD'),
];

// Проверка подключения к базе данных через MySQLi
function connectToDatabase(array $dbConfig): bool {
    $maxAttempts = intval(getenv('DB_MAX_ATTEMPTS') ?? 5); // Максимальное количество попыток
    $attemptDelay = intval(getenv('DB_ATTEMPT_DELAY') ?? 2); // Задержка между попытками (в секундах)

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            // Создаём новое соединение через MySQLi
            $mysqli = new \mysqli(
                $dbConfig['host'],
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['database'],
                $dbConfig['port']
            );

            // Проверяем успешность подключения
            if ($mysqli->connect_error) {
                throw new \Exception("Ошибка подключения: " . $mysqli->connect_error);
            }

            // Устанавливаем кодировку UTF-8
            $mysqli->set_charset("utf8mb4");

            echo "Подключение к базе данных успешно установлено.\n";
            return true;
        } catch (\Exception $e) {
            if ($attempt === $maxAttempts) {
                // Если это последняя попытка, выбрасываем ошибку
                echo "Ошибка подключения к базе данных (попытка #$attempt): " . $e->getMessage() . "\n";
                return false;
            }

            echo "Не удалось подключиться к базе данных (попытка #$attempt). Повторная попытка через {$attemptDelay} секунд...\n";
            sleep($attemptDelay);
        }
    }

    return false;
}

// Выполняем проверку подключения
if (!connectToDatabase($dbConfig)) {
    exit(1); // Прерываем выполнение скрипта, если подключение не удалось
}

// Инициализация Eloquent
$capsule = new Capsule;

// Передаём конфигурацию Eloquent
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $dbConfig['host'],
    'port' => $dbConfig['port'],
    'database' => $dbConfig['database'],
    'username' => $dbConfig['username'],
    'password' => $dbConfig['password'],
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

// Запуск Eloquent
$capsule->setAsGlobal();
$capsule->bootEloquent();