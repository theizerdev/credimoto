<?php

namespace App\Traits;

use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

trait Exportable
{
    public function export()
    {
        $query = $this->getExportQuery();
        $headers = $this->getExportHeaders();
        $data = $query->get()->map(fn($row) => $this->formatExportRow($row));
        
        $modelName = class_basename($query->getModel());
        $filename = strtolower($modelName) . '_' . now()->format('Y-m-d_His') . '.xlsx';
        
        return Excel::download(new class($data, $headers) implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize {
            private $data;
            private $headers;
            
            public function __construct($data, $headers)
            {
                $this->data = $data;
                $this->headers = $headers;
            }
            
            public function collection(): \Illuminate\Support\Enumerable
            {
                return $this->data;
            }
            
            public function headings(): array
            {
                return $this->headers;
            }
            
            public function styles(Worksheet $sheet): ?array
            {
                $lastColumn = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();
                
                $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                ]);
                
                if ($lastRow > 1) {
                    $sheet->getStyle('A2:' . $lastColumn . $lastRow)->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'EEEEEE']]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    
                    for ($i = 2; $i <= $lastRow; $i += 2) {
                        $sheet->getStyle('A' . $i . ':' . $lastColumn . $i)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9FAFB']],
                        ]);
                    }
                }
                
                $sheet->getRowDimension(1)->setRowHeight(25);
                return [];
            }
        }, $filename);
    }
    
    abstract protected function getExportQuery();
    abstract protected function getExportHeaders();
    abstract protected function formatExportRow($row);
}
