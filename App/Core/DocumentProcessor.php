<?php

namespace App\Core;

use App\Services\DocumentHandlerFactory;
use App\Services\ThumbnailCreator;
use App\Models\Document;
use App\Services\Loggers;
use App\Utils\DateFormatter;
use App\Utils\DateFormats;
use App\Utils\FileHashCalculator;


class DocumentProcessor
{
    private $config;
    private $factory;
    private $thumbnailCreator;
    private $logFile;
    private $logger;
    private $dateFormatter;
    private $fileHashCalculator;

    public function __construct(array $config, Loggers $logger)
    {
        $this->config = $config;
        $this->factory = new DocumentHandlerFactory();
        
        $this->logFile = __DIR__ . '/logs/main.log';
        $this->logger = new Loggers($this->logFile);
        // Инициализируем DateFormatter с поддерживаемыми форматами
        $this->dateFormatter = new DateFormatter([DateFormats::YMD, DateFormats::DMY, DateFormats::MDY]);
        $this->fileHashCalculator = new FileHashCalculator($logger);
        $this->thumbnailCreator = new ThumbnailCreator(
            $config['thumbnail_size'],
            $this->fileHashCalculator,
            $logger
        );
    }

    public function processDirectory(): array
    {
        $results = [];
        $allowedExtensions = $this->config['allowed_extensions'];
        $directory = $this->config['directory'];
        $thumbnailDir = $this->config['thumbnail_directory'];

        if (!is_dir($directory)) {
            $this->logger->info("Указанная директория не существует: $directory", 'error');
            echo "Указанная директория не существует.\n";
            return $results;
        }

        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0777, true);
            $this->logger->info("Создана директория для миниатюр: $thumbnailDir", 'info');
        }

        //   $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $iterator =  new \RecursiveIteratorIterator(
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
                                continue; // Пропускаем обработку дубликата
                            }

                            list($title, $creationDate) = $handler->getInfo($file_path);

                            // Нормализуем дату с помощью DateFormatter
                            $normalizedCreationDate = $this->dateFormatter->convertToDateOrCurrent($creationDate);

                            // Создаём миниатюру с именем, основанном на хеше
                            $thumbnailPath = $this->thumbnailCreator->createThumbnail($file_path, $thumbnailDir, $ext);

                            // Сохраняем данные в базу данных
                            $document = new Document();
                            $document->path = $file_path;
                            $document->title = $title;
                            $document->creation_date = $normalizedCreationDate; // Используем нормализованную дату
                            $document->thumbnail_path = $thumbnailPath ?? null;
                            $document->hash = $hash;

                            $document->save();
                            $this->logger->info("Документ успешно сохранён: $file_path");

                            $results[] = [
                                'path' => $file_path,
                                'title' => $title,
                                'creation_date' => $normalizedCreationDate,
                                'thumbnail' => $thumbnailPath,
                                'hash' => $hash,
                            ];
                        } catch (\Exception $e) {
                            $this->logger->error("Ошибка обработки файла $file_path: {$e->getMessage()}");
                        }
                    } else {
                        $this->logger->info("Нет обработчика для расширения: $ext", 'warning');
                    }
                }
            }
        }

        return $results;
    }

    public function printResults(array $results): void
    {
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
}
