<?php

namespace App\Handlers;

use App\Services\Loggers as Logger;

/**
 * Абстрактный класс для обработчиков документов.
 */
abstract class AbstractDocumentHandler {
    protected Logger $logger;

    /**
     * Конструктор класса.
     *
     * @param Logger $logger Логгер для записи событий и ошибок.
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }

    /**
     * Проверяет, поддерживает ли обработчик заданное расширение файла.
     *
     * @param string $extension Расширение файла.
     * @return bool Возвращает true, если обработчик поддерживает это расширение, иначе false.
     */
    abstract public function supports(string $extension): bool;

    /**
     * Извлекает метаданные из документа.
     *
     * @param string $filePath Путь к файлу.
     * @return array Массив с информацией о документе [название, дата создания].
     */
    abstract public function getInfo(string $filePath): array;

    /**
     * Нормализует дату.
     *
     * @param string $date Исходная дата.
     * @return string Нормализованная дата.
     */
    protected function normalizeDate(string $date): string {
        return !empty($date) ? $date : "Дата не указана";
    }
}