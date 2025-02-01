<?php

declare(strict_types=1);

// Загрузка переменных окружения из .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $envVariables = parse_ini_file($envFile);
    if ($envVariables === false) {
        die("Error: Unable to parse .env file.\n");
    }
    foreach ($envVariables as $key => $value) {
        putenv("$key=$value");
    }
} else {
    die("Error: .env file not found.\n");
}

class MySQLConnectionTester
{
    private string $host;
    private int $port;
    private string $dbname;
    private string $username;
    private string $password;
    private int $maxAttempts;
    private int $attemptDelay;

    public function __construct(
        string $host,
        int $port,
        string $dbname,
        string $username,
        string $password,
        int $maxAttempts = 3,
        int $attemptDelay = 2
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->dbname = $dbname;
        $this->username = $username;
        $this->password = $password;
        $this->maxAttempts = $maxAttempts;
        $this->attemptDelay = $attemptDelay;
    }

    public function testPDOConnection(): void
    {
        $attempt = 1;
        while ($attempt <= $this->maxAttempts) {
            try {
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ];

                $pdo = new PDO($dsn, $this->username, $this->password, $options);
                echo "PDO: Connection successful!\n";
                return;
            } catch (PDOException $e) {
                echo "PDO: Attempt $attempt failed: " . $e->getMessage() . "\n";
                $attempt++;
                if ($attempt <= $this->maxAttempts) {
                    sleep($this->attemptDelay);
                }
            }
        }
        echo "PDO: All attempts failed. Could not connect to the database.\n";
    }

    public function testMySQLiConnection(): void
    {
        $attempt = 1;
        while ($attempt <= $this->maxAttempts) {
            try {
                $mysqli = new mysqli($this->host, $this->username, $this->password, $this->dbname, $this->port);

                if ($mysqli->connect_error) {
                    throw new Exception($mysqli->connect_error);
                }

                echo "MySQLi: Connection successful!\n";
                $mysqli->close();
                return;
            } catch (Exception $e) {
                echo "MySQLi: Attempt $attempt failed: " . $e->getMessage() . "\n";
                $attempt++;
                if ($attempt <= $this->maxAttempts) {
                    sleep($this->attemptDelay);
                }
            }
        }
        echo "MySQLi: All attempts failed. Could not connect to the database.\n";
    }
}

// Получение переменных окружения
$host = getenv('DB_HOST');
$port = (int)getenv('DB_PORT');
$dbname = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$maxAttempts = (int)getenv('MAX_ATTEMPTS');
$attemptDelay = (int)getenv('ATTEMPT_DELAY');

// Проверка наличия всех переменных
if ($host === false || $port === false || $dbname === false || $username === false || $password === false) {
    die("Error: Missing required environment variables.\n");
}

// Создаем экземпляр тестера
$tester = new MySQLConnectionTester($host, $port, $dbname, $username, $password, $maxAttempts, $attemptDelay);

// Тестируем подключение через PDO
echo "Testing PDO connection...\n";
$tester->testPDOConnection();

// Тестируем подключение через MySQLi
echo "\nTesting MySQLi connection...\n";
$tester->testMySQLiConnection();