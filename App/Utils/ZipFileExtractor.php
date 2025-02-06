<?php

namespace App\Utils;

use App\Services\Loggers as Logger;

class ZipFileExtractor {
    private $logger;

    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }

    /**
     * Проверяет ZIP-архив на наличие поддерживаемых типов файлов и извлекает их.
     *
     * @param string $zipPath Путь к ZIP-архиву.
     * @param string $outputDir Директория для сохранения извлечённых файлов.
     * @param array $allowedExtensions Поддерживаемые расширения файлов.
     * @return array Массив с путями к извлечённым файлам.
     */
    public function extractSupportedFiles(string $zipPath, string $outputDir, array $allowedExtensions): array {
        try {
            // Проверяем существование файла
            if (!file_exists($zipPath)) {
                $this->logger->error("ZIP-архив не найден: $zipPath");
                return [];
            }

            // Открываем ZIP-архив
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                $this->logger->error("Не удалось открыть ZIP-архив: $zipPath");
                return [];
            }

            // Создаём временную директорию для извлечения файлов
            $tempExtractDir = tempnam(sys_get_temp_dir(), 'zip_extract_');
            if (!mkdir($tempExtractDir)) {
                $this->logger->error("Не удалось создать временную директорию для извлечения ZIP-файлов.");
                $zip->close();
                return [];
            }

            // Извлекаем все файлы из архива
            $zip->extractTo($tempExtractDir);
            $zip->close();

            // Находим поддерживаемые файлы
            $extractedFiles = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tempExtractDir)) as $file) {
                if ($file->isFile()) {
                    $ext = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
                    if (in_array($ext, $allowedExtensions)) {
                        $extractedFiles[] = $file->getPathname();
                    }
                }
            }

            // Удаляем временную директорию после завершения
            $this->deleteDirectory($tempExtractDir);

            if (empty($extractedFiles)) {
                $this->logger->info("В ZIP-архиве $zipPath нет поддерживаемых файлов.");
            } else {
                $this->logger->info("Из ZIP-архива $zipPath извлечены файлы: " . implode(', ', $extractedFiles));
            }

            return $extractedFiles;

        } catch (\Exception $e) {
            $this->logger->error("Ошибка обработки ZIP-архива $zipPath: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Удаляет директорию и все её содержимое.
     *
     * @param string $dir Путь к директории.
     */
    private function deleteDirectory(string $dir): void {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = "$dir/$file";
                if (is_dir($path)) {
                    $this->deleteDirectory($path); // Рекурсивное удаление поддиректорий
                } else {
                    unlink($path); // Удаление файлов
                }
            }
            rmdir($dir); // Удаление самой директории
            $this->logger->debug("Временная директория удалена: $dir");
        }
    }
}