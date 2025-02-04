<?php

namespace App\Utils;

// Enum для хранения поддерживаемых форматов даты
enum DateFormats: string {
    case YMD = 'Y-m-d';   // Формат YYYY-MM-DD
    case DMY = 'd.m.Y';   // Формат DD.MM.YYYY
    case MDY = 'm/d/Y';   // Формат MM/DD/YYYY
}