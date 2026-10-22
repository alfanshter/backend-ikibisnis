<?php

namespace App\Services;

use App\Models\DocumentNumbering;
use Carbon\Carbon;

class DocumentNumberingService
{
    public static function generateNumber(string $module): string
    {
        $numbering = DocumentNumbering::where('module', $module)->first();

        if (!$numbering) {
            $prefix = strtoupper(substr($module, 0, 3));
            return $prefix . '-' . date('Ymd') . '-' . rand(1000, 9999);
        }

        $next = max($numbering->current_number + 1, $numbering->starting_number);
        $numbering->update(['current_number' => $next]);

        return self::formatString($numbering->format ?? '[SEQ]', $next, $numbering->digit);
    }

    public static function formatPreview($numbering): string
    {
        if (!$numbering) return '';
        $next = max($numbering->current_number + 1, $numbering->starting_number);
        return self::formatString($numbering->format ?? '[SEQ]', $next, $numbering->digit);
    }

    private static function formatString(string $format, int $seqNumber, int $digit): string
    {
        $now = Carbon::now();
        $seq = str_pad($seqNumber, $digit, '0', STR_PAD_LEFT);
        
        $romans = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
        $romanMonth = $romans[$now->month - 1];

        $format = str_replace('[YYYY]', $now->format('Y'), $format);
        $format = str_replace('[YY]', $now->format('y'), $format);
        $format = str_replace('[MM]', $now->format('m'), $format);
        $format = str_replace('[DD]', $now->format('d'), $format);
        $format = str_replace('[ROMAN_MONTH]', $romanMonth, $format);
        $format = str_replace('[SEQ]', $seq, $format);

        return $format;
    }
}
