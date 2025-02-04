<?php

namespace App\Handlers;

class DjvuHandler extends AbstractDocumentHandler {
    public function supports(string $extension): bool {
        return $extension === 'djvu';
    }

    public function getInfo(string $filePath): array {
        $title = "Нет заголовка";
        $creationDate = "null";

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