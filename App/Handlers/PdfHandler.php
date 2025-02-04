<?php

namespace App\Handlers;

abstract class AbstractDocumentHandler {
    abstract public function supports(string $extension): bool;
    abstract public function getInfo(string $filePath): array;

    protected function normalizeDate(string $date): string {
        return !empty($date) ? $date : "Дата не указана";
    }
}

class PdfHandler extends AbstractDocumentHandler {
    public function supports(string $extension): bool {
        return $extension === 'pdf';
    }

    public function getInfo(string $filePath): array {
        $title = "Нет заголовка";
        $creationDate = "null";

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