<?php

declare(strict_types=1);

namespace App\Context\Lead;

/**
 * A UTF-8 CSV with a header row and one lead per line; the header names are the JSON field names.
 */
final readonly class LeadCsv
{
    public const MAX_BYTES = 1048576;
    public const MAX_ROWS = 1000;

    /**
     * @return list<array<string, string>>
     */
    public static function rows(string $csv): array
    {
        if (strlen($csv) > self::MAX_BYTES) {
            throw new LeadRejectedException(['file' => 'The file must be 1 MB or smaller']);
        }
        if (str_starts_with($csv, "\xEF\xBB\xBF")) {
            $csv = substr($csv, 3);
        }
        if (!mb_check_encoding($csv, 'UTF-8')) {
            throw new LeadRejectedException(['file' => 'The file must be UTF-8']);
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new LeadRejectedException(['file' => 'The file could not be read']);
        }
        fwrite($stream, $csv);
        rewind($stream);

        try {
            $header = fgetcsv($stream, 0, ',', '"', '');
            if (!is_array($header) || $header === [null]) {
                throw new LeadRejectedException(['file' => 'The file has no header row']);
            }
            $names = array_map(static fn (?string $name): string => strtolower(trim((string) $name)), $header);
            if (!in_array('phone', $names, true)) {
                throw new LeadRejectedException(['file' => 'The header row must use the lead field names, like phone,country_code,ip']);
            }

            $rows = [];
            while (($cells = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                if ($cells === [null]) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw new LeadRejectedException(['file' => 'The file has more than 1000 leads']);
                }
                $row = [];
                foreach ($names as $position => $name) {
                    if ($name !== '') {
                        $row[$name] = trim((string) ($cells[$position] ?? ''));
                    }
                }
                $rows[] = $row;
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }
}
