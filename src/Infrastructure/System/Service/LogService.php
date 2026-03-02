<?php

declare(strict_types=1);

namespace Pet\Infrastructure\System\Service;

class LogService
{
    private const LOG_FILE_PATH = WP_CONTENT_DIR . '/debug.log';
    private const MAX_LINES = 200;

    public function getRecentEntries(int $lines = self::MAX_LINES, ?string $filter = null): array
    {
        if (!file_exists(self::LOG_FILE_PATH)) {
            return [];
        }

        // Efficiently read last N lines
        $entries = $this->readLastLines(self::LOG_FILE_PATH, $lines * 2); // Read double to account for filtering

        if ($filter) {
            $entries = array_filter($entries, function ($line) use ($filter) {
                return strpos((string)$line, $filter) !== false;
            });
        }
        
        // Ensure values are strings and re-index
        $entries = array_map('strval', array_values($entries));

        // Re-slice to exact requested count after filtering
        return array_slice($entries, -$lines);
    }

    public function generateDiagnosticReport(): string
    {
        $report = "PET DIAGNOSTIC REPORT\n";
        $report .= "Generated at: " . date('Y-m-d H:i:s') . "\n";
        $report .= str_repeat("=", 50) . "\n\n";

        // Section 1: PET Specific Logs
        $report .= "SECTION 1: PET SYSTEM LOGS (Last 200 Entries)\n";
        $report .= str_repeat("-", 30) . "\n";
        $petLogs = $this->getRecentEntries(200, '[PET');
        if (empty($petLogs)) {
            $report .= "No PET specific logs found.\n";
        } else {
            $report .= implode("", $petLogs);
        }
        $report .= "\n\n";

        // Section 2: WP Debug Log
        $report .= "SECTION 2: WP DEBUG LOG (Last 200 Entries)\n";
        $report .= str_repeat("-", 30) . "\n";
        $wpLogs = $this->getRecentEntries(200);
        if (empty($wpLogs)) {
            $report .= "Debug log is empty or not accessible.\n";
        } else {
            $report .= implode("", $wpLogs);
        }

        return $report;
    }

    private function readLastLines(string $filePath, int $lines): array
    {
        $handle = fopen($filePath, "r");
        if (!$handle) {
            return [];
        }

        $linesFound = 0;
        $chunkSize = 4096;
        $buffer = '';

        // Go to end
        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);
        
        if ($fileSize === 0) {
            fclose($handle);
            return [];
        }

        $pos = $fileSize;
        
        // Read backwards in chunks
        while ($pos > 0 && $linesFound <= $lines) {
            $readSize = min($chunkSize, $pos);
            $pos -= $readSize;
            
            fseek($handle, $pos);
            $chunk = fread($handle, $readSize);
            
            // Prepend to buffer
            $buffer = $chunk . $buffer;
            
            // Count newlines in this chunk
            $linesFound += substr_count($chunk, "\n");
        }
        
        fclose($handle);
        
        // Split buffer into lines
        // Use regex split to handle different newline types if needed, but explode is faster for standard log files
        $fileLines = explode("\n", $buffer);
        
        // Remove empty last line if present (files usually end with newline)
        if (end($fileLines) === '') {
            array_pop($fileLines);
        }
        
        // Take last N lines
        return array_slice($fileLines, -$lines);
    }
}
