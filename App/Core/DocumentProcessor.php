<?php

namespace App\Core;

use App\Services\DocumentHandlerFactory;
use App\Services\ThumbnailCreator;
use App\Utils\FileHashCalculator;
use App\Utils\DateFormatter;
use App\Utils\DateFormats;
use App\Services\Loggers as Logger;
use App\Models\Document;

class DocumentProcessor {
    private $config;
    private $factory;
    private $thumbnailCreator;
    private $fileHashCalculator;
    private $dateFormatter;
    private $logger;

    public function __construct(array $config, Logger $logger) {
        $this->config = $config;
        $this->factory = new DocumentHandlerFactory();
        $this->fileHashCalculator = new FileHashCalculator($logger);
        $this->dateFormatter = new DateFormatter([DateFormats::YMD, DateFormats::DMY, DateFormats::MDY]);
        $this->logger = $logger;

        // Передаём FileHashCalculator в ThumbnailCreator
        $this->thumbnailCreator = new ThumbnailCreator(
            $config['thumbnail_size'],
            $this->fileHashCalculator,
            $logger
        );
    }

    /**
     * Процесс обработки директории: поиск документов, их обработка и сохранение результатов.
     *
     * @return array Массив с результатами обработки документов.
     */
    public function processDirectory(): array {
        $results = [];
        $allowedExtensions = $this->config['allowed_extensions'];
        $directory = $this->config['directory'];
        $outputDir = $this->config['output_directory'];

        if (!is_dir($directory)) {
            $this->logger->error("Указанная директория не существует: $directory");
            echo "Указанная директория не существует.\n";
            return $results;
        }

        // Используем улучшенную строку итератора
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::KEY_AS_PATHNAME),
            \RecursiveIteratorIterator::SELF_FIRST,
            \RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExtensions)) {
                    $file_path = $file->getPathname();
                    $handler = $this->factory->getHandlerForExtension($ext);

                    if ($handler) {
                        try {
                            // Вычисляем хеш файла
                            $hash = $this->fileHashCalculator->calculateHash($file_path);

                            // Проверяем, существует ли документ с таким хешем
                            if ($this->fileHashCalculator->findHash($hash)) {
                                $this->logger->info("Документ с хешем $hash уже существует в базе данных.");
                                continue; // Пропускаем дубликат
                            }

                            list($title, $creationDate) = $handler->getInfo($file_path);

                            // Нормализуем дату
                            $normalizedCreationDate = $this->dateFormatter->convertToDateOrCurrent($creationDate);

                            // Создаём директорию с названием файла
                            $baseName = pathinfo($file_path, PATHINFO_FILENAME); // Базовое имя файла без расширения
                            $docOutputDir = "$outputDir/$baseName";
                            if (!is_dir($docOutputDir)) {
                                mkdir($docOutputDir, 0777, true);
                                $this->logger->info("Создана директория для файла: $docOutputDir");
                            }

                            // Копируем исходный файл в новую директорию
                            $newFilePath = "$docOutputDir/" . basename($file_path);
                            copy($file_path, $newFilePath);
                            $this->logger->info("Исходный файл скопирован: $newFilePath");

                            // Создаём миниатюру
                            $thumbnailPath = $this->thumbnailCreator->createThumbnail($file_path, $docOutputDir, $ext);
                            if ($thumbnailPath) {
                                $this->logger->info("Миниатюра создана: $thumbnailPath");
                            } else {
                                $this->logger->warning("Миниатюра не создана для файла: $file_path");
                            }

                            // Генерируем JSON-файл с метаданными
                            $this->generateJsonMetadata($docOutputDir, $title, $normalizedCreationDate, $thumbnailPath);

                            // Сохраняем данные в базу данных с новым путём к файлу
                            $document = new Document();
                            $document->path = $newFilePath; // Новый путь к файлу
                            $document->title = $title;
                            $document->creation_date = $normalizedCreationDate;
                            $document->thumbnail_path = $thumbnailPath ?? null;
                            $document->hash = $hash;

                            $document->save();
                            $this->logger->info("Документ успешно сохранён: $newFilePath");

                            $results[] = [
                                'path' => $newFilePath, // Новый путь к файлу
                                'title' => $title,
                                'creation_date' => $normalizedCreationDate,
                                'thumbnail' => $thumbnailPath,
                                'hash' => $hash,
                            ];
                        } catch (\Exception $e) {
                            $this->logger->error("Ошибка обработки файла $file_path: {$e->getMessage()}");
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Выводит результаты обработки документов в консоль.
     *
     * @param array $results Массив с результатами обработки документов.
     */
    public function printResults(array $results): void {
        if (!empty($results)) {
            echo "\nНайденные документы:\n";
            foreach ($results as $result) {
                echo "Путь: " . $result['path'] . "\n";
                echo "Название: " . $result['title'] . "\n";
                echo "Дата создания: " . $result['creation_date'] . "\n";
                echo "Хеш: " . $result['hash'] . "\n";

                if ($result['thumbnail']) {
                    echo "Миниатюра: " . $result['thumbnail'] . "\n";
                } else {
                    echo "Миниатюра: Не создана\n";
                }

                echo str_repeat("-", 40) . "\n";
            }
        } else {
            echo "Документы не найдены.\n";
        }
    }

    /**
     * Генерирует JSON-файл с метаданными о документе.
     *
     * @param string $outputDir Директория для сохранения JSON-файла.
     * @param string $title Название документа.
     * @param string $creationDate Дата создания документа.
     * @param string|null $thumbnailPath Путь к миниатюре.
     */
    private function generateJsonMetadata(string $outputDir, string $title, string $creationDate, ?string $thumbnailPath): void {
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
    }
}