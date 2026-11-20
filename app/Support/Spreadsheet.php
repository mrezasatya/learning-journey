<?php

namespace App\Support;

use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Baca/tulis Excel (.xlsx) & CSV memakai OpenSpout. */
class Spreadsheet
{
    /**
     * Membaca sheet pertama → array baris asosiatif berdasarkan header (huruf kecil, spasi → _).
     *
     * @return array<int, array<string, mixed>>  kunci = nomor baris di file (mulai 2)
     */
    public static function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);
        if (in_array($extension, ['csv', 'txt'], true)) {
            $first = (string) fgets(fopen($path, 'r'));
            $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
            $reader = new CsvReader(new CsvOptions(FIELD_DELIMITER: $delimiter));
        } else {
            $reader = new XlsxReader;
        }

        $reader->open($path);
        $rows = [];
        $header = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            $line = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $line++;
                $values = $row->toArray();
                if ($header === null) {
                    $header = array_map(fn ($h) => self::normalizeHeader((string) $h), $values);
                    continue;
                }
                if (count(array_filter($values, fn ($v) => $v !== null && $v !== '')) === 0) {
                    continue;
                }
                $assoc = [];
                foreach ($header as $i => $key) {
                    if ($key === '') {
                        continue;
                    }
                    $v = $values[$i] ?? null;
                    $assoc[$key] = $v instanceof DateTimeInterface ? $v->format('Y-m-d') : (is_string($v) ? trim($v) : $v);
                }
                $rows[$line] = $assoc;
            }
            break; // hanya sheet pertama
        }
        $reader->close();

        return $rows;
    }

    public static function normalizeHeader(string $h): string
    {
        $h = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)));

        return trim(preg_replace('/[^a-z0-9]+/', '_', $h), '_');
    }

    /**
     * Unduhan .xlsx. $sheets = ['Nama sheet' => [header[], rows[][]]]
     */
    public static function download(string $filename, array $sheets): StreamedResponse
    {
        return new StreamedResponse(function () use ($sheets) {
            $writer = new XlsxWriter;
            $writer->openToFile('php://output');
            self::writeSheets($writer, $sheets);
            $writer->close();
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function writeFile(string $path, array $sheets): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        self::writeSheets($writer, $sheets);
        $writer->close();
    }

    private static function writeSheets(XlsxWriter $writer, array $sheets): void
    {
        $bold = (new Style)->withFontBold(true)->withBackgroundColor('D9E8F0');
        $first = true;
        foreach ($sheets as $name => [$header, $rows]) {
            $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName(mb_substr($name, 0, 31));
            $first = false;
            $writer->addRow(Row::fromValuesWithStyle($header, $bold));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($v) => $v instanceof DateTimeInterface ? $v->format('Y-m-d') : $v, array_values($r))));
            }
        }
    }
}
