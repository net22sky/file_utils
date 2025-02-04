<?php

namespace App\Utils;

use App\Models\Document;
use App\Services\Loggers;

class FileHashCalculator {
    private $logger;

    public function __construct(Loggers $logger) {
        $this->logger = $logger;
    }

    /**
     * Вычисляет хеш файла.
     *
     * @param string $filePath Путь к файлу.
     * @return string Хеш файла.
     * @throws \Exception Если файл не существует.
     */
    public function calculateHash(string $filePath): string {
        if (!file_exists($filePath)) {
            $this->logger->error("Файл не существует: $filePath");
            throw new \Exception("Файл не существует: $filePath");
        }

        // Используем алгоритм SHA-256 для генерации хеша
        return hash_file('sha256', $filePath);
    }

    /**
     * Ищет документ по хешу в базе данных.
     *
     * @param string $hash Хеш файла.
     * @return bool Возвращает true, если документ существует, иначе false.
     */
    public function findHash(string $hash): bool {
        // Проверяем, существует ли документ с указанным хешем
        $document = Document::where('hash', $hash)->first();
        return $document !== null;
    }
}