<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Models/Document.php';

use Models\Document;

/**
 * Читает конфигурацию из файла.
 */
function readConfig(string $configPath): array
{
    if (!file_exists($configPath)) {
        echo "Файл конфигурации не найден.\n";
        exit(1);
    }

    $config = json_decode(file_get_contents($configPath), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Ошибка при чтении файла конфигурации: " . json_last_error_msg() . "\n";
        exit(1);
    }

    return $config;
}

/**
 * Абстрактный класс для обработчиков документов.
 */
abstract class AbstractDocumentHandler
{
    abstract public function supports(string $extension): bool;
    abstract public function getInfo(string $filePath): array;

    protected function normalizeDate(string $date): string
    {
        return !empty($date) ? $date : "Дата не указана";
    }
}

class PdfHandler extends AbstractDocumentHandler
{
    public function supports(string $extension): bool
    {
        return $extension === 'pdf';
    }

    public function getInfo(string $filePath): array
    {
        $title = "Нет заголовка";
        $creationDate = "Дата не указана";

        $command = "pdfinfo \"$filePath\" 2>/dev/null";
        $output = shell_exec($command);

        if ($output !== null) {
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (strpos($line, "Title:") === 0) {
                    $title = trim(substr($line, 6));
                } elseif (strpos($line, "CreationDate:") === 0) {
                    $creationDate = trim(substr($line, 13));
                }
            }
        }

        return [$title, $this->normalizeDate($creationDate)];
    }
}

class Fb2Handler extends AbstractDocumentHandler
{
    public function supports(string $extension): bool
    {
        return $extension === 'fb2';
    }

    public function getInfo(string $filePath): array
    {
        $title = "Нет заголовка";
        $date = "Дата не указана";

        try {
            $xml = simplexml_load_file($filePath);
            if ($xml && isset($xml->description->titleInfo->book_title)) {
                $title = (string)$xml->description->titleInfo->book_title;
            }
            if ($xml && isset($xml->description->publishInfo->year)) {
                $date = (string)$xml->description->publishInfo->year;
            }
        } catch (Exception $e) {
            echo "Ошибка при чтении FB2-файла $filePath: " . $e->getMessage() . "\n";
        }

        return [$title, $this->normalizeDate($date)];
    }
}

class DjvuHandler extends AbstractDocumentHandler
{
    public function supports(string $extension): bool
    {
        return $extension === 'djvu';
    }

    public function getInfo(string $filePath): array
    {
        $title = "Нет заголовка";
        $creationDate = "Дата не указана";

        $command = "djvutxt \"$filePath\" 2>/dev/null";
        $output = shell_exec($command);

        if ($output !== null) {
            if (preg_match('/^(\S.*)$/m', $output, $matches)) {
                $title = trim($matches[1]);
            }
        }

        return [$title, $this->normalizeDate($creationDate)];
    }
}

class DocumentHandlerFactory
{
    private $handlers;

    public function __construct()
    {
        $this->handlers = [
            new PdfHandler(),
            new Fb2Handler(),
            new DjvuHandler()
        ];
    }

    public function getHandlerForExtension(string $extension): ?AbstractDocumentHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($extension)) {
                return $handler;
            }
        }
        return null;
    }
}

class ThumbnailCreator
{
    private $thumbnailSize;

    public function __construct(array $thumbnailSize)
    {
        $this->thumbnailSize = $thumbnailSize;
    }

    public function createThumbnail(string $docPath, string $outputDir, string $docType): ?string
    {
        $baseName = basename($docPath, "." . pathinfo($docPath, PATHINFO_EXTENSION));
        $thumbnailPath = "$outputDir/$baseName.png";

        if ($docType === 'pdf') {
            //$command = "convert -density 300 \"" . $docPath . "[0]\" -resize " . $this->thumbnailSize['width'] . "x" . $this->thumbnailSize['height'] . " \"" . $thumbnailPath . "\"";
            $command = 'convert -density 300 "' . $docPath . '[0]" -resize ' . $this->thumbnailSize['width'] . 'x' . $this->thumbnailSize['height'] . ' "' . $thumbnailPath . '"';
            //$command = "convert -density 300 \"$docPath"[0] -resize {$this->thumbnailSize['width']}x{$this->thumbnailSize['height']} \"$thumbnailPath\"";
            shell_exec($command);
        } elseif ($docType === 'djvu') {
            $command = "ddjvu -page=1 -format=pnm \"$docPath\" temp.pnm && pnmtopng temp.pnm > \"$thumbnailPath\" && rm temp.pnm";
            shell_exec($command);
        }

        return file_exists($thumbnailPath) ? $thumbnailPath : null;
    }
}

class DocumentProcessor
{
    private $config;
    private $factory;
    private $thumbnailCreator;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->factory = new DocumentHandlerFactory();
        $this->thumbnailCreator = new ThumbnailCreator($config['thumbnail_size']);
    }

    public function processDirectory(): array
    {
        $results = [];
        $allowedExtensions = $this->config['allowed_extensions'];
        $directory = $this->config['directory'];
        $thumbnailDir = $this->config['thumbnail_directory'];

        if (!is_dir($directory)) {
            echo "Указанная директория не существует.\n";
            return $results;
        }

        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0777, true);
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExtensions)) {
                    $file_path = $file->getPathname();
                    $handler = $this->factory->getHandlerForExtension($ext);
                    if ($handler) {
                        list($title, $creationDate) = $handler->getInfo($file_path);
                        $thumbnailPath = $this->thumbnailCreator->createThumbnail($file_path, $thumbnailDir, $ext);

                        // Сохраняем данные в базу данных
                        $document = new Document();
                        $document->path = $file_path;
                        $document->title = $title;
                        $document->creation_date = $creationDate;
                        $document->thumbnail_path = $thumbnailPath ?? null;
                        $document->save();

                        $results[] = [
                            'path' => $file_path,
                            'title' => $title,
                            'creation_date' => $creationDate,
                            'thumbnail' => $thumbnailPath
                        ];
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
// Основной блок выполнения
$configPath = "config.json"; // Путь к файлу конфигурации
$config = readConfig($configPath);

echo "Поиск документов...\n";
$processor = new DocumentProcessor($config);
$results = $processor->processDirectory();

if (!empty($results)) {
    $processor->printResults($results);
} else {
    echo "Документы не найдены.\n";
}

echo "Готово! Данные сохранены в базу данных.\n";
