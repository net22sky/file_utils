<?php

namespace App\Handlers;

use Exception;
use App\Handlers\AbstractDocumentHandler;

class Fb2Handler extends AbstractDocumentHandler {
    public function supports(string $extension): bool {
        return $extension === 'fb2';
    }

    public function getInfo(string $filePath): array {
        $title = "Нет заголовка";
        $date = "null";

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