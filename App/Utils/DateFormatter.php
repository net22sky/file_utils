<?php

namespace App\Utils;

use DateTime;

class DateFormatter {
    // Readonly свойство для хранения массива поддерживаемых форматов
    public readonly array $supportedFormats;

    // Конструктор с инициализацией форматов
    public function __construct(array $formats = [DateFormats::YMD, DateFormats::DMY, DateFormats::MDY]) {
        $this->supportedFormats = array_map(fn(DateFormats $format) => $format->value, $formats);
    }

    /**
     * Преобразует строку в дату или возвращает текущую дату, если входная строка некорректна.
     *
     * @param string|null $date Строка с датой или null
     * @return string Дата в формате MySQL (YYYY-MM-DD)
     */
    public function convertToDateOrCurrent(?string $date): string {
        if (empty($date)) {
            return $this->getCurrentDate(); // Возвращаем текущую дату
        }

        foreach ($this->supportedFormats as $format) {
            $dateTime = DateTime::createFromFormat($format, $date);
            if ($dateTime && $dateTime->format($format) === $date) {
                return $dateTime->format('Y-m-d'); // Преобразуем в формат MySQL
            }
        }

        return $this->getCurrentDate(); // Если ни один формат не подошел, возвращаем текущую дату
    }

    /**
     * Возвращает текущую дату в формате MySQL (YYYY-MM-DD).
     *
     * @return string
     */
    private function getCurrentDate(): string {
        return date('Y-m-d');
    }
}