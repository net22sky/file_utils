<?php

declare(strict_types=1);

class MySQLConnectionTester
{
    private string $host;
    private string $dbname;
    private string $username;
    private string $password;
    private int $maxAttempts;
    private int $attemptDelay;

    public function __construct(string $host, string $dbname, string $username, string $password, int $maxAttempts = 3, int $attemptDelay = 2)
    {
        $this->host = $host;
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
                $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8";
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
                $mysqli = new mysqli($this->host, $this->username, $this->password, $this->dbname);

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

// Параметры подключения к базе данных
$host = '127.0.0.1';
$dbname = 'document_db';
$username = 'root';
$password = 'secret';

// Создаем экземпляр тестера
$tester = new MySQLConnectionTester($host, $dbname, $username, $password);

// Тестируем подключение через PDO
echo "Testing PDO connection...\n";
$tester->testPDOConnection();

// Тестируем подключение через MySQLi
echo "\nTesting MySQLi connection...\n";
$tester->testMySQLiConnection();