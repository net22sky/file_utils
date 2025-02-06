<?php

namespace App\Services;

use App\Interfaces\IDataManager;
use App\Services\Loggers as Logger;
use App\Models\Document;

class DataManager implements IDataManager {
    private $logger;

    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }

    /**
     * Сохраняет документ в базу данных.
     *
     * @param string $filePath Путь к файлу.
     * @param string $title Название документа.
     * @param string $creationDate Дата создания документа.
     * @param string|null $thumbnailPath Путь к миниатюре или null, если миниатюра отсутствует.
     * @param string $hash Хеш файла.
     */
    public function saveDocumentToDatabase(
        string $filePath,
        string $title,
        string $creationDate,
        ?string $thumbnailPath,
        string $hash
    ): void {
        try {
            // Создаём запись в базе данных
            $document = new Document();
            $document->path = $filePath;
            $document->title = $title;
            $document->creation_date = $creationDate;
            $document->thumbnail_path = $thumbnailPath ?? null;
            $document->hash = $hash;

            $document->save();
            $this->logger->info("Документ успешно сохранён: $filePath");
        } catch (\Exception $e) {
            $this->logger->error("Ошибка сохранения документа в базу данных: $filePath. Сообщение: {$e->getMessage()}");
        }
    }

    /**
     * Генерирует JSON-файл с метаданными о документе.
     *
     * @param string $outputDir Директория для сохранения JSON-файла.
     * @param string $title Название документа.
     * @param string $creationDate Дата создания документа.
     * @param string|null $thumbnailPath Путь к миниатюре или null, если миниатюра отсутствует.
     */
    public function generateJsonMetadata(
        string $outputDir,
        string $title,
        string $creationDate,
        ?string $thumbnailPath
    ): void {
        try {
            $jsonFilePath = "$outputDir/metadata.json";

            // Подготавливаем метаданные
            $metadata = [
                'identifier' => uniqid(),
                'title' => $title,
                'date' => $creationDate,
                'language' => 'ru',
                'thumbnail' => $thumbnailPath ? basename($thumbnailPath) : null,
            ];

            // Сохраняем JSON-файл
            file_put_contents($jsonFilePath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->logger->info("JSON-файл с метаданными создан: $jsonFilePath");
        } catch (\Exception $e) {
            $this->logger->error("Ошибка генерации JSON-метаданных для файла: $outputDir. Сообщение: {$e->getMessage()}");
        }
    }
}