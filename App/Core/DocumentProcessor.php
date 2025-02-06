<?php

namespace App\Core;

use App\Services\DocumentHandlerFactory;
use App\Services\ThumbnailCreator;
use App\Utils\FileHashCalculator;
use App\Utils\DateFormatter;
use App\Utils\DateFormats;
use App\Utils\ZipFileExtractor;
use App\Services\Loggers as Logger;
use App\Interfaces\IDataManager;
use App\Models\Document;

class DocumentProcessor {
    private $config;
    private $factory;
    private $thumbnailCreator;
    private $fileHashCalculator;
    private $dateFormatter;
    private $logger;
    private $dataManager;

    public function __construct(array $config, Logger $logger, IDataManager $dataManager) {
        $this->config = $config;
        $this->factory = new DocumentHandlerFactory($logger);
        $this->fileHashCalculator = new FileHashCalculator($logger);
        $this->dateFormatter = new DateFormatter([DateFormats::YMD, DateFormats::DMY, DateFormats::MDY]);
        $this->logger = $logger;
        $this->dataManager = $dataManager;

        // Передаём FileHashCalculator в ThumbnailCreator
        $this->thumbnailCreator = new ThumbnailCreator(
            ['width' => getenv('THUMBNAIL_WIDTH') ?? 800, 'height' => getenv('THUMBNAIL_HEIGHT') ?? 600],
            $this->fileHashCalculator,
            $this->logger
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

        // Создаём экземпляр ZipFileExtractor
        $zipExtractor = new ZipFileExtractor($this->logger);

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filePath = $file->getPathname();
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

                if ($ext === 'zip') {
                    // Обработка ZIP-архива
                    $zipExtractor->extractSupportedFiles($filePath, $outputDir, $allowedExtensions, function ($extractedFile, $outputDir, $extension) use (&$results) {
                        $this->processSingleFile($extractedFile, $outputDir, $extension, $results);
                    });
                } elseif (in_array($ext, $allowedExtensions)) {
                    // Обработка обычных файлов
                    $this->processSingleFile($filePath, $outputDir, $ext, $results);
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
     * Обрабатывает один файл: создаёт миниатюру, генерирует JSON-метаданные и сохраняет его в базу данных.
     *
     * @param string $filePath Путь к файлу.
     * @param string $outputDir Директория для сохранения результатов.
     * @param string $extension Расширение файла.
     * @param array &$results Ссылка на массив с результатами обработки.
     */
    private function processSingleFile(string $filePath, string $outputDir, string $extension, array &$results): void {
        try {
            // Вычисляем хеш файла
            $hash = $this->fileHashCalculator->calculateHash($filePath);

            // Проверяем, существует ли документ с таким хешем
            if ($this->fileHashCalculator->findHash($hash)) {
                $this->logger->info("Документ с хешем $hash уже существует в базе данных.");
                return; // Пропускаем дубликат
            }

            // Находим соответствующий обработчик
            $handler = $this->factory->getHandlerForExtension($extension);
            if (!$handler) {
                $this->logger->warning("Обработчик для расширения $extension не найден.");
                return;
            }

            list($title, $creationDate) = $handler->getInfo($filePath);

            // Нормализуем дату
            $normalizedCreationDate = $this->dateFormatter->convertToDateOrCurrent($creationDate);

            // Создаём директорию с названием файла
            $baseName = pathinfo($filePath, PATHINFO_FILENAME);
            $docOutputDir = "$outputDir/$baseName";
            if (!is_dir($docOutputDir)) {
                mkdir($docOutputDir, 0777, true);
                $this->logger->info("Создана директория для файла: $docOutputDir");
            }

            // Копируем исходный файл в новую директорию
            $newFilePath = "$docOutputDir/" . basename($filePath);
            if (!copy($filePath, $newFilePath)) {
                $this->logger->error("Не удалось скопировать файл: $filePath -> $newFilePath");
                throw new \Exception("Не удалось скопировать файл: $filePath");
            }
            $this->logger->info("Файл скопирован: $newFilePath");

            // Создаём миниатюру
            $thumbnailPath = $this->thumbnailCreator->createThumbnail($filePath, $docOutputDir, $extension);
            if ($thumbnailPath) {
                $this->logger->info("Миниатюра создана: $thumbnailPath");
            } else {
                $this->logger->warning("Миниатюра не создана для файла: $filePath");
            }

            // Сохраняем данные в базу данных через IDataManager
            $this->dataManager->saveDocumentToDatabase(
                $newFilePath,
                $title,
                $normalizedCreationDate,
                $thumbnailPath,
                $hash
            );

            // Генерируем JSON-метаданные через IDataManager
            $this->dataManager->generateJsonMetadata($docOutputDir, $title, $normalizedCreationDate, $thumbnailPath);

            $results[] = [
                'path' => $newFilePath,
                'title' => $title,
                'creation_date' => $normalizedCreationDate,
                'thumbnail' => $thumbnailPath,
                'hash' => $hash,
            ];

        } catch (\Exception $e) {
            $this->logger->error("Ошибка обработки файла $filePath: {$e->getMessage()}");
        }
    }
}