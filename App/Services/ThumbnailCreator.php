<?php

namespace App\Services;

use App\Utils\FileHashCalculator;
use App\Services\Loggers as Logger;


use SimpleXMLElement;

class ThumbnailCreator {
    private $thumbnailSize;
    private $fileHashCalculator; // Для вычисления хешей файлов
    private $logger;

    /**
     * Конструктор класса ThumbnailCreator.
     *
     * @param array $thumbnailSize Размер миниатюры (ширина и высота).
     * @param FileHashCalculator $fileHashCalculator Компонент для работы с хешами.
     * @param Logger $logger Логгер для записи событий и ошибок.
     */
    public function __construct(array $thumbnailSize, FileHashCalculator $fileHashCalculator, Logger $logger) {
        $this->thumbnailSize = $thumbnailSize;
        $this->fileHashCalculator = $fileHashCalculator;
        $this->logger = $logger;
    }

    /**
     * Создаёт миниатюру документа.
     *
     * @param string $docPath Путь к документу.
     * @param string $outputDir Директория для сохранения миниатюры.
     * @param string $docType Тип документа ('pdf', 'djvu' или 'fb2').
     * @return string|null Путь к созданной миниатюре или null, если создание не удалось.
     */
    public function createThumbnail(string $docPath, string $outputDir, string $docType): ?string {
        try {
            // Вычисляем хеш файла
            $hash = $this->fileHashCalculator->calculateHash($docPath);

            // Генерируем имя миниатюры
            $thumbnailName = $hash . '.png';
            $thumbnailPath = "$outputDir/$thumbnailName";

            // Проверяем, существует ли миниатюра
            if (file_exists($thumbnailPath)) {
                $this->logger->info("Миниатюра для файла $docPath уже существует: $thumbnailPath");
                return $thumbnailPath;
            }

            // Создаём миниатюру в зависимости от типа документа
            if ($docType === 'pdf') {
                $command = 'convert -density 300 "' . $docPath . '[0]" -resize ' . $this->thumbnailSize['width'] . 'x' . $this->thumbnailSize['height'] . ' "' . $thumbnailPath . '"';
                shell_exec($command);
            } elseif ($docType === 'djvu') {
                $command = 'ddjvu -page=1 -format=pnm "' . $docPath . '" temp.pnm && pnmtopng temp.pnm > "' . $thumbnailPath . '" && rm temp.pnm';
                shell_exec($command);
            } elseif ($docType === 'fb2') {
                // Извлекаем обложку из FB2
                $coverImagePath = $this->extractFb2Cover($docPath, $outputDir);

                if ($coverImagePath) {
                    // Создаём миниатюру на основе обложки
                    $command = 'convert "' . $coverImagePath . '" -resize ' . $this->thumbnailSize['width'] . 'x' . $this->thumbnailSize['height'] . ' "' . $thumbnailPath . '"';
                    shell_exec($command);

                    // Удаляем временную обложку
                    unlink($coverImagePath);
                }
            }

            // Проверяем, успешно ли создана миниатюра
            if (file_exists($thumbnailPath)) {
                $this->logger->info("Миниатюра успешно создана: $thumbnailPath");
                return $thumbnailPath;
            }

            $this->logger->error("Не удалось создать миниатюру для файла: $docPath");
            return null;

        } catch (\Exception $e) {
            $this->logger->error("Ошибка создания миниатюры для файла $docPath: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Извлекает обложку из FB2-файла и сохраняет её во временном файле.
     *
     * @param string $fb2Path Путь к FB2-файлу.
     * @param string $outputDir Директория для временного сохранения обложки.
     * @return string|null Путь к временному файлу обложки или null, если обложка отсутствует.
     */
    private function extractFb2Cover(string $fb2Path, string $outputDir): ?string {
        try {
            // Проверяем существование файла
            if (!file_exists($fb2Path)) {
                $this->logger->warning("FB2-файл не найден: $fb2Path");
                return null;
            }
    
            // Загружаем содержимое FB2-файла как SimpleXMLElement
            $xml = simplexml_load_file($fb2Path);
            if (!$xml) {
                $this->logger->error("Ошибка загрузки FB2-файла: $fb2Path");
                return null;
            }
    
            // Ищем ссылку на обложку (<coverpage/image>)
            $coverPage = $xml->xpath('//description/title-info/coverpage/image');
            if (empty($coverPage)) {
                $this->logger->info("Обложка не найдена в FB2-файле: $fb2Path");
                return null;
            }
    
            // Получаем ID обложки
            $coverId = (string)$coverPage[0]['l:href'];
            if (empty($coverId)) {
                $this->logger->warning("ID обложки не указан в FB2-файле: $fb2Path");
                return null;
            }
            $coverId = ltrim($coverId, '#'); // Убираем символ '#'
    
            // Находим бинарные данные обложки (<binary>)
            $binaryData = $xml->xpath("//binary[@id='$coverId']");
            if (empty($binaryData)) {
                $this->logger->warning("Бинарные данные обложки не найдены в FB2-файле: $fb2Path (ID: $coverId)");
                return null;
            }
    
            // Получаем MIME-тип и Base64-данные
            $mimeType = (string)$binaryData[0]['content-type'];
            $base64Data = (string)$binaryData[0];
    
            if (empty($base64Data)) {
                $this->logger->warning("Base64-данные обложки пусты в FB2-файле: $fb2Path (ID: $coverId, MIME: $mimeType)");
                return null;
            }
    
            if (strpos($mimeType, 'image/') !== 0) {
                $this->logger->warning("Неподдерживаемый тип обложки в FB2-файле: $fb2Path ($mimeType)");
                return null;
            }
    
            // Генерируем имя временного файла обложки
            $tempCoverPath = tempnam(sys_get_temp_dir(), 'fb2_cover_');
            if ($tempCoverPath === false) {
                $this->logger->error("Не удалось создать временный файл для обложки из FB2-файла: $fb2Path");
                return null;
            }
    
            // Декодируем Base64-данные и сохраняем их во временном файле
            if (file_put_contents($tempCoverPath, base64_decode($base64Data)) === false) {
                $this->logger->error("Ошибка сохранения обложки из FB2-файла: $fb2Path (ID: $coverId, MIME: $mimeType)");
                return null;
            }
    
            $this->logger->info("Обложка успешно извлечена из FB2-файла: $fb2Path (ID: $coverId, MIME: $mimeType)");
            return $tempCoverPath;
    
        } catch (\Exception $e) {
            $this->logger->error("Ошибка обработки FB2-файла $fb2Path: {$e->getMessage()}");
            return null;
        }
    }
}