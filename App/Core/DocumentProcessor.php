<?php
namespace App\Core;

use App\Services\DocumentHandlerFactory;
use App\Services\ThumbnailCreator;
use App\Utils\FileHashCalculator;
use App\Utils\DateFormatter;
use App\Utils\DateFormats;
use App\Services\Loggers as Logger;
use App\Models\Document;
use Ramsey\Uuid\Uuid;
use App\Utils\ZipFileExtractor;

class DocumentProcessor {
    private $config;
    private $factory;
    private $thumbnailCreator;
    private $fileHashCalculator;
    private $dateFormatter;
    private $logger;

    public function __construct(array $config, Logger $logger) {
        $this->config = $config;
        $this->factory = new DocumentHandlerFactory($logger);
        $this->fileHashCalculator = new FileHashCalculator($logger);
        $this->dateFormatter = new DateFormatter([DateFormats::YMD, DateFormats::DMY, DateFormats::MDY]);
        $this->logger = $logger;

        // Передаём FileHashCalculator в ThumbnailCreator
        $this->thumbnailCreator = new ThumbnailCreator(
            [
                'width' => getenv('THUMBNAIL_WIDTH') ?? 800,
                'height' => getenv('THUMBNAIL_HEIGHT') ?? 600,
            ],
            $this->fileHashCalculator,
            $logger
        );

        // Создаём корневую директорию для вывода, если её нет
        if (!is_dir($this->config['output_directory'])) {
            mkdir($this->config['output_directory'], 0777, true);
            $this->logger->info("Создана корневая директория для вывода: " . $this->config['output_directory']);
        }
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
                    $extractedFiles = $zipExtractor->extractSupportedFiles($filePath, $outputDir, $allowedExtensions);
                    foreach ($extractedFiles as $extractedFile) {
                        $this->processSingleFile($extractedFile, $outputDir, pathinfo($extractedFile, PATHINFO_EXTENSION));
                    }
                } elseif (in_array($ext, $allowedExtensions)) {
                    // Обработка обычных файлов
                    $this->processSingleFile($filePath, $outputDir, $ext);
                }
            }
        }
    
        return $results;
    }
    
    /**
     * Обрабатывает один файл: создаёт миниатюру, генерирует JSON-метаданные и сохраняет его в базу данных.
     *
     * @param string $filePath Путь к файлу.
     * @param string $outputDir Директория для сохранения результатов.
     * @param string $extension Расширение файла.
     */
    private function processSingleFile(string $filePath, string $outputDir, string $extension): void {
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
    
            // Генерируем JSON-метаданные
            $metadata = [
                'title' => $title,
                'creation_date' => $normalizedCreationDate,
                'language' => 'ru',
                'thumbnail' => file_exists($thumbnailPath) ? basename($thumbnailPath) : null,
            ];
            $this->generateJsonMetadata($docOutputDir, $metadata, $thumbnailPath);
    
            // Сохраняем данные в базу данных
            $document = new Document();
            $document->path = $newFilePath;
            $document->title = $title;
            $document->creation_date = $normalizedCreationDate;
            $document->thumbnail_path = $thumbnailPath ?? null;
            $document->hash = $hash;
    
            $document->save();
            $this->logger->info("Документ успешно сохранён: $newFilePath");
    
        } catch (\Exception $e) {
            $this->logger->error("Ошибка обработки файла $filePath: {$e->getMessage()}");
        }
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
            'identifier' => Uuid::uuid4()->toString(),
            'title' => $title,
            'date' => $creationDate,
            'language' => 'ru',
            'thumbnail' => file_exists($thumbnailPath) ? basename($thumbnailPath) : null,
        ];

        // Сохраняем JSON-файл
        file_put_contents($jsonFilePath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->logger->info("JSON-файл с метаданными создан: $jsonFilePath");
    }
}