<?php

namespace App\Interfaces;

use App\Models\Document;

interface IDataManager {
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
    ): void;

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
    ): void;
}