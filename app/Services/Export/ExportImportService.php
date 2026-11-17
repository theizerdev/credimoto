<?php

namespace App\Services\Export;

use App\Services\Logging\LoggingService;
use App\Services\Cache\CacheService;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use League\Csv\Writer as LeagueCsvWriter;
use League\Csv\Reader as LeagueCsvReader;
use Exception;
use Ramsey\Uuid\Uuid;

class ExportImportService
{
    protected LoggingService $loggingService;
    protected CacheService $cacheService;
    protected AuditService $auditService;
    protected array $supportedFormats = ['xlsx', 'csv', 'json', 'xml'];
    protected int $chunkSize = 1000;
    protected int $maxFileSize = 50 * 1024 * 1024; // 50MB

    public function __construct(
        LoggingService $loggingService,
        CacheService $cacheService,
        AuditService $auditService
    ) {
        $this->loggingService = $loggingService;
        $this->cacheService = $cacheService;
        $this->auditService = $auditService;
    }

    public function exportData(string $modelClass, array $options = []): array
    {
        try {
            $exportId = Uuid::uuid4()->toString();
            $format = $options['format'] ?? 'xlsx';
            $filters = $options['filters'] ?? [];
            $columns = $options['columns'] ?? [];
            $filename = $options['filename'] ?? $this->generateFilename($modelClass, $format);
            $includeHeaders = $options['include_headers'] ?? true;
            $dateFormat = $options['date_format'] ?? 'Y-m-d H:i:s';

            if (!in_array($format, $this->supportedFormats)) {
                throw new Exception("Unsupported format: {$format}");
            }

            $this->loggingService->logBusinessEvent('export_started', [
                'export_id' => $exportId,
                'model' => $modelClass,
                'format' => $format,
                'filters' => $filters,
                'columns' => $columns,
            ]);

            $query = $this->buildQuery($modelClass, $filters);
            $totalRecords = $query->count();

            if ($totalRecords === 0) {
                return [
                    'success' => false,
                    'message' => 'No records found for export',
                    'export_id' => $exportId,
                ];
            }

            $filePath = $this->generateExportFile($query, $format, $filename, $columns, $includeHeaders, $dateFormat);
            $fileSize = Storage::size($filePath);

            $this->auditService->logBulkOperation('export', $modelClass, [], [
                'export_id' => $exportId,
                'format' => $format,
                'total_records' => $totalRecords,
                'file_size' => $fileSize,
                'file_path' => $filePath,
                'columns' => $columns,
                'filters' => $filters,
            ]);

            $this->loggingService->logBusinessEvent('export_completed', [
                'export_id' => $exportId,
                'model' => $modelClass,
                'format' => $format,
                'total_records' => $totalRecords,
                'file_size' => $fileSize,
            ]);

            return [
                'success' => true,
                'export_id' => $exportId,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'total_records' => $totalRecords,
                'download_url' => $this->generateDownloadUrl($filePath),
                'expires_at' => now()->addHours(24)->toIso8601String(),
            ];
        } catch (Exception $e) {
            $this->loggingService->logBusinessError($e, [
                'export_data' => $modelClass,
                'options' => $options,
            ]);
            return [
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
                'export_id' => $exportId ?? null,
            ];
        }
    }

    public function importData(string $filePath, string $modelClass, array $options = []): array
    {
        try {
            $importId = Uuid::uuid4()->toString();
            $format = $options['format'] ?? $this->detectFormat($filePath);
            $chunkSize = $options['chunk_size'] ?? $this->chunkSize;
            $validateOnly = $options['validate_only'] ?? false;
            $updateExisting = $options['update_existing'] ?? false;
            $skipErrors = $options['skip_errors'] ?? false;
            $mapping = $options['mapping'] ?? [];

            if (!Storage::exists($filePath)) {
                throw new Exception("File not found: {$filePath}");
            }

            $fileSize = Storage::size($filePath);
            if ($fileSize > $this->maxFileSize) {
                throw new Exception("File size exceeds maximum allowed size ({$this->maxFileSize} bytes)");
            }

            if (!in_array($format, $this->supportedFormats)) {
                throw new Exception("Unsupported format: {$format}");
            }

            $this->loggingService->logBusinessEvent('import_started', [
                'import_id' => $importId,
                'model' => $modelClass,
                'format' => $format,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'validate_only' => $validateOnly,
                'update_existing' => $updateExisting,
            ]);

            $data = $this->readImportFile($filePath, $format, $mapping);
            $validationResults = $this->validateImportData($data, $modelClass);

            if (!$validateOnly && $validationResults['valid']) {
                $importResults = $this->processImportData($data, $modelClass, $updateExisting, $skipErrors, $chunkSize);

                $this->auditService->logBulkOperation('import', $modelClass, $importResults['ids'] ?? [], [
                    'import_id' => $importId,
                    'format' => $format,
                    'total_processed' => $importResults['total_processed'],
                    'successful' => $importResults['successful'],
                    'failed' => $importResults['failed'],
                    'file_size' => $fileSize,
                    'update_existing' => $updateExisting,
                    'skip_errors' => $skipErrors,
                ]);

                $this->loggingService->logBusinessEvent('import_completed', [
                    'import_id' => $importId,
                    'model' => $modelClass,
                    'format' => $format,
                    'total_processed' => $importResults['total_processed'],
                    'successful' => $importResults['successful'],
                    'failed' => $importResults['failed'],
                ]);

                return [
                    'success' => true,
                    'import_id' => $importId,
                    'total_records' => count($data),
                    'processed' => $importResults['total_processed'],
                    'successful' => $importResults['successful'],
                    'failed' => $importResults['failed'],
                    'errors' => $importResults['errors'] ?? [],
                ];
            } else {
                return [
                    'success' => $validateOnly,
                    'import_id' => $importId,
                    'total_records' => count($data),
                    'validation' => $validationResults,
                    'errors' => $validationResults['errors'] ?? [],
                ];
            }
        } catch (Exception $e) {
            $this->loggingService->logBusinessError($e, [
                'import_data' => $modelClass,
                'file_path' => $filePath,
                'options' => $options,
            ]);
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'import_id' => $importId ?? null,
            ];
        }
    }

    public function getExportTemplate(string $modelClass, array $options = []): array
    {
        try {
            $format = $options['format'] ?? 'xlsx';
            $columns = $options['columns'] ?? $this->getModelColumns($modelClass);
            $includeHeaders = $options['include_headers'] ?? true;
            $sampleData = $options['include_sample_data'] ?? false;
            $filename = $options['filename'] ?? $this->generateFilename($modelClass, $format, 'template');

            $data = [];

            if ($includeHeaders) {
                $data[] = $columns;
            }

            if ($sampleData) {
                $sampleRow = $this->generateSampleData($modelClass, $columns);
                $data[] = $sampleRow;
            }

            $filePath = $this->generateExportFile(new Collection($data), $format, $filename, $columns, false, 'Y-m-d H:i:s');

            return [
                'success' => true,
                'file_path' => $filePath,
                'download_url' => $this->generateDownloadUrl($filePath),
                'columns' => $columns,
            ];
        } catch (Exception $e) {
            $this->loggingService->logBusinessError($e, [
                'get_export_template' => $modelClass,
                'options' => $options,
            ]);
            return [
                'success' => false,
                'message' => 'Template generation failed: ' . $e->getMessage(),
            ];
        }
    }

    public function getExportHistory(int $limit = 50): array
    {
        try {
            $cacheKey = 'export_history';

            return $this->cacheService->remember($cacheKey, 3600, function () use ($limit) {
                $exports = $this->auditService->searchAuditLogs([
                    'action' => 'bulk.export',
                ], $limit);

                return array_map(function ($export) {
                    return [
                        'export_id' => $export['metadata']['export_id'] ?? null,
                        'model' => $export['metadata']['model_class'] ?? 'Unknown',
                        'format' => $export['metadata']['format'] ?? 'Unknown',
                        'total_records' => $export['metadata']['total_records'] ?? 0,
                        'file_size' => $export['metadata']['file_size'] ?? 0,
                        'created_at' => $export['created_at'],
                        'user_email' => $export['user_email'] ?? 'System',
                    ];
                }, $exports);
            });
        } catch (Exception $e) {
            $this->loggingService->logBusinessError($e, [
                'get_export_history' => $limit,
            ]);
            return [];
        }
    }

    public function getImportHistory(int $limit = 50): array
    {
        try {
            $cacheKey = 'import_history';

            return $this->cacheService->remember($cacheKey, 3600, function () use ($limit) {
                $imports = $this->auditService->searchAuditLogs([
                    'action' => 'bulk.import',
                ], $limit);

                return array_map(function ($import) {
                    return [
                        'import_id' => $import['metadata']['import_id'] ?? null,
                        'model' => $import['metadata']['model_class'] ?? 'Unknown',
                        'format' => $import['metadata']['format'] ?? 'Unknown',
                        'total_processed' => $import['metadata']['total_processed'] ?? 0,
                        'successful' => $import['metadata']['successful'] ?? 0,
                        'failed' => $import['metadata']['failed'] ?? 0,
                        'file_size' => $import['metadata']['file_size'] ?? 0,
                        'created_at' => $import['created_at'],
                        'user_email' => $import['user_email'] ?? 'System',
                    ];
                }, $imports);
            });
        } catch (Exception $e) {
            $this->loggingService->logBusinessError($e, [
                'get_import_history' => $limit,
            ]);
            return [];
        }
    }

    public function cleanupOldFiles(int $days = 7): array
    {
        try {
            $cutoffDate = now()->subDays($days);
            $exportPath = 'exports';
            $importPath = 'imports';

            $deletedExports = $this->cleanupDirectory($exportPath, $cutoffDate);
            $deletedImports = $this->cleanupDirectory($importPath, $cutoffDate);

            $this->loggingService->logBusinessEvent('cleanup_completed', [
                'deleted_exports' => $deletedExports,
                'deleted_imports' => $deletedImports,
                'days' => $days,
            ]);

            return [
                'success' => true,
                'deleted_exports' => $deletedExports,
                'deleted_imports' => $deletedImports,
                'total_deleted' => $deletedExports + $deletedImports,
            ];
        } catch (Exception $e) {
            $this->loggingService->logBusinessError($e, [
                'cleanup_old_files' => $days,
            ]);
            return [
                'success' => false,
                'message' => 'Cleanup failed: ' . $e->getMessage(),
            ];
        }
    }

    protected function buildQuery(string $modelClass, array $filters)
    {
        $query = $modelClass::query();

        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                if (isset($value['operator']) && isset($value['value'])) {
                    $query->where($field, $value['operator'], $value['value']);
                } elseif (isset($value['from']) && isset($value['to'])) {
                    $query->whereBetween($field, [$value['from'], $value['to']]);
                } else {
                    $query->whereIn($field, $value);
                }
            } else {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    protected function generateExportFile($query, string $format, string $filename, array $columns, bool $includeHeaders, string $dateFormat): string
    {
        $filePath = "exports/{$filename}";

        switch ($format) {
            case 'xlsx':
                $this->generateExcelFile($query, $filePath, $columns, $includeHeaders, $dateFormat);
                break;
            case 'csv':
                $this->generateCsvFile($query, $filePath, $columns, $includeHeaders, $dateFormat);
                break;
            case 'json':
                $this->generateJsonFile($query, $filePath, $columns, $dateFormat);
                break;
            case 'xml':
                $this->generateXmlFile($query, $filePath, $columns, $dateFormat);
                break;
            default:
                throw new Exception("Unsupported format: {$format}");
        }

        return $filePath;
    }

    protected function generateExcelFile($query, string $filePath, array $columns, bool $includeHeaders, string $dateFormat): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $row = 1;

        if ($includeHeaders) {
            $col = 1;
            foreach ($columns as $column) {
                $sheet->setCellValue([$col, $row], $this->formatColumnHeader($column));
                $col++;
            }
            $row++;
        }

        $query->chunk($this->chunkSize, function ($records) use ($sheet, &$row, $columns, $dateFormat) {
            foreach ($records as $record) {
                $col = 1;
                foreach ($columns as $column) {
                    $value = data_get($record, $column);
                    if ($value instanceof \DateTime) {
                        $value = $value->format($dateFormat);
                    }
                    $sheet->setCellValue([$col, $row], $value);
                    $col++;
                }
                $row++;
            }
        });

        $writer = new Xlsx($spreadsheet);
        $tempPath = storage_path('app/temp_export.xlsx');
        $writer->save($tempPath);

        Storage::put($filePath, file_get_contents($tempPath));
        unlink($tempPath);
    }

    protected function generateCsvFile($query, string $filePath, array $columns, bool $includeHeaders, string $dateFormat): void
    {
        $tempPath = storage_path('app/temp_export.csv');
        $csv = LeagueCsvWriter::createFromPath($tempPath, 'w+');

        if ($includeHeaders) {
            $headers = array_map([$this, 'formatColumnHeader'], $columns);
            $csv->insertOne($headers);
        }

        $query->chunk($this->chunkSize, function ($records) use ($csv, $columns, $dateFormat) {
            foreach ($records as $record) {
                $row = [];
                foreach ($columns as $column) {
                    $value = data_get($record, $column);
                    if ($value instanceof \DateTime) {
                        $value = $value->format($dateFormat);
                    }
                    $row[] = $value;
                }
                $csv->insertOne($row);
            }
        });

        Storage::put($filePath, file_get_contents($tempPath));
        unlink($tempPath);
    }

    protected function generateJsonFile($query, string $filePath, array $columns, string $dateFormat): void
    {
        $data = [];

        $query->chunk($this->chunkSize, function ($records) use (&$data, $columns, $dateFormat) {
            foreach ($records as $record) {
                $row = [];
                foreach ($columns as $column) {
                    $value = data_get($record, $column);
                    if ($value instanceof \DateTime) {
                        $value = $value->format($dateFormat);
                    }
                    $row[$column] = $value;
                }
                $data[] = $row;
            }
        });

        Storage::put($filePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    protected function generateXmlFile($query, string $filePath, array $columns, string $dateFormat): void
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><root></root>');

        $query->chunk($this->chunkSize, function ($records) use ($xml, $columns, $dateFormat) {
            foreach ($records as $record) {
                $item = $xml->addChild('item');
                foreach ($columns as $column) {
                    $value = data_get($record, $column);
                    if ($value instanceof \DateTime) {
                        $value = $value->format($dateFormat);
                    }
                    $item->addChild($column, htmlspecialchars((string)$value));
                }
            }
        });

        Storage::put($filePath, $xml->asXML());
    }

    protected function readImportFile(string $filePath, string $format, array $mapping = []): array
    {
        $fullPath = Storage::path($filePath);

        switch ($format) {
            case 'xlsx':
            case 'csv':
                return $this->readSpreadsheetFile($fullPath, $format, $mapping);
            case 'json':
                return json_decode(Storage::get($filePath), true) ?? [];
            case 'xml':
                return $this->readXmlFile($fullPath, $mapping);
            default:
                throw new Exception("Unsupported format: {$format}");
        }
    }

    protected function readSpreadsheetFile(string $filePath, string $format, array $mapping): array
    {
        if ($format === 'csv') {
            $reader = new CsvReader();
            $spreadsheet = $reader->load($filePath);
        } else {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $spreadsheet = $reader->load($filePath);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $data = [];
        $headers = [];
        $firstRow = true;

        foreach ($sheet->getRowIterator() as $row) {
            $rowData = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                $value = $cell->getValue();
                if ($firstRow) {
                    $headers[] = $this->mapColumnHeader($value, $mapping);
                } else {
                    $rowData[] = $value;
                }
            }

            if (!$firstRow && !empty(array_filter($rowData))) {
                $data[] = array_combine($headers, $rowData);
            }

            $firstRow = false;
        }

        return $data;
    }

    protected function readXmlFile(string $filePath, array $mapping): array
    {
        $xml = simplexml_load_file($filePath);
        $data = [];

        foreach ($xml->item as $item) {
            $row = [];
            foreach ($item as $key => $value) {
                $mappedKey = $this->mapColumnHeader($key, $mapping);
                $row[$mappedKey] = (string)$value;
            }
            $data[] = $row;
        }

        return $data;
    }

    protected function validateImportData(array $data, string $modelClass): array
    {
        $errors = [];
        $warnings = [];
        $valid = true;

        if (empty($data)) {
            return [
                'valid' => false,
                'errors' => ['No data found in file'],
                'warnings' => [],
            ];
        }

        $model = new $modelClass();
        $fillable = $model->getFillable();
        $required = $this->getRequiredFields($modelClass);

        foreach ($data as $index => $row) {
            $rowErrors = [];
            $rowWarnings = [];

            foreach ($required as $field) {
                if (!isset($row[$field]) || empty($row[$field])) {
                    $rowErrors[] = "Row " . ($index + 2) . ": Field '{$field}' is required";
                }
            }

            foreach ($row as $key => $value) {
                if (!in_array($key, $fillable) && !str_starts_with($key, '_')) {
                    $rowWarnings[] = "Row " . ($index + 2) . ": Field '{$key}' is not fillable";
                }
            }

            if (!empty($rowErrors)) {
                $errors = array_merge($errors, $rowErrors);
                $valid = false;
            }

            if (!empty($rowWarnings)) {
                $warnings = array_merge($warnings, $rowWarnings);
            }
        }

        return [
            'valid' => $valid && empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'total_rows' => count($data),
        ];
    }

    protected function processImportData(array $data, string $modelClass, bool $updateExisting, bool $skipErrors, int $chunkSize): array
    {
        $results = [
            'total_processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'ids' => [],
            'errors' => [],
        ];

        $chunks = array_chunk($data, $chunkSize);

        foreach ($chunks as $chunk) {
            DB::transaction(function () use ($chunk, $modelClass, $updateExisting, $skipErrors, &$results) {
                foreach ($chunk as $row) {
                    try {
                        $model = $this->findOrCreateModel($modelClass, $row, $updateExisting);

                        if ($model) {
                            $model->fill($row);
                            $model->save();

                            $results['ids'][] = $model->id;
                            $results['successful']++;
                        }
                    } catch (Exception $e) {
                        $results['failed']++;
                        if (!$skipErrors) {
                            throw $e;
                        }
                        $results['errors'][] = $e->getMessage();
                    }

                    $results['total_processed']++;
                }
            });
        }

        return $results;
    }

    protected function findOrCreateModel(string $modelClass, array $data, bool $updateExisting)
    {
        $uniqueFields = $this->getUniqueFields($modelClass);

        if (!empty($uniqueFields)) {
            $query = $modelClass::query();

            foreach ($uniqueFields as $field) {
                if (isset($data[$field])) {
                    $query->where($field, $data[$field]);
                }
            }

            $existing = $query->first();

            if ($existing) {
                return $updateExisting ? $existing : null;
            }
        }

        return new $modelClass();
    }

    protected function generateFilename(string $modelClass, string $format, string $suffix = ''): string
    {
        $modelName = class_basename($modelClass);
        $timestamp = now()->format('Y-m-d_H-i-s');
        $suffix = $suffix ? "_{$suffix}" : '';

        return "{$modelName}{$suffix}_{$timestamp}.{$format}";
    }

    protected function generateDownloadUrl(string $filePath): string
    {
        return Storage::temporaryUrl($filePath, now()->addHours(24));
    }

    protected function detectFormat(string $filePath): string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        if (in_array($extension, $this->supportedFormats)) {
            return $extension;
        }

        throw new Exception("Cannot detect file format for: {$filePath}");
    }

    protected function formatColumnHeader(string $column): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $column));
    }

    protected function mapColumnHeader(string $header, array $mapping): string
    {
        return $mapping[$header] ?? $header;
    }

    protected function getModelColumns(string $modelClass): array
    {
        $model = new $modelClass();
        return $model->getFillable();
    }

    protected function getRequiredFields(string $modelClass): array
    {
        $model = new $modelClass();
        $rules = method_exists($model, 'rules') ? $model->rules() : [];

        $required = [];
        foreach ($rules as $field => $rule) {
            if (is_string($rule) && str_contains($rule, 'required')) {
                $required[] = $field;
            } elseif (is_array($rule) && in_array('required', $rule)) {
                $required[] = $field;
            }
        }

        return $required;
    }

    protected function getUniqueFields(string $modelClass): array
    {
        $model = new $modelClass();
        $rules = method_exists($model, 'rules') ? $model->rules() : [];

        $unique = [];
        foreach ($rules as $field => $rule) {
            if (is_string($rule) && str_contains($rule, 'unique')) {
                $unique[] = $field;
            } elseif (is_array($rule)) {
                foreach ($rule as $r) {
                    if (is_string($r) && str_contains($r, 'unique')) {
                        $unique[] = $field;
                        break;
                    }
                }
            }
        }

        return $unique;
    }

    protected function generateSampleData(string $modelClass, array $columns): array
    {
        $sampleData = [];

        foreach ($columns as $column) {
            switch ($column) {
                case 'name':
                    $sampleData[$column] = 'Sample Name';
                    break;
                case 'email':
                    $sampleData[$column] = 'sample@example.com';
                    break;
                case 'phone':
                    $sampleData[$column] = '+1234567890';
                    break;
                case 'status':
                    $sampleData[$column] = 'active';
                    break;
                case 'created_at':
                case 'updated_at':
                    $sampleData[$column] = now()->format('Y-m-d H:i:s');
                    break;
                default:
                    $sampleData[$column] = 'Sample ' . ucfirst($column);
            }
        }

        return $sampleData;
    }

    protected function cleanupDirectory(string $directory, \DateTimeInterface $cutoffDate): int
    {
        $deleted = 0;
        $files = Storage::files($directory);

        foreach ($files as $file) {
            $lastModified = Storage::lastModified($file);
            if ($lastModified < $cutoffDate->getTimestamp()) {
                Storage::delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
