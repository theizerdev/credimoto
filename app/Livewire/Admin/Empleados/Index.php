<?php

namespace App\Livewire\Admin\Empleados;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Traits\HasDynamicLayout;
use App\Traits\Exportable;
use App\Models\Empleado;

class Index extends Component
{
    use WithPagination, HasDynamicLayout, Exportable, WithFileUploads;

    public $search = '';
    public $activo = '';
    public $perPage = 10;
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    public $importFile;

    protected $queryString = [
        'search' => ['except' => ''],
        'activo' => ['except' => ''],
        'perPage' => ['except' => 10],
        'sortBy' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc']
    ];

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'activo', 'perPage']);
        $this->resetPage();
    }
    
    public function import()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt,xlsx'
        ]);
        $ext = strtolower($this->importFile->getClientOriginalExtension());
        $count = 0;
        if ($ext === 'xlsx') {
            $rows = \Maatwebsite\Excel\Facades\Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\Import {}, $this->importFile);
            $dataRows = $rows[0] ?? [];
            $header = array_map('strtolower', array_map('trim', $dataRows[0] ?? []));
            foreach (array_slice($dataRows, 1) as $row) {
                $mapped = [];
                foreach ($header as $i => $key) {
                    $mapped[$key] = $row[$i] ?? null;
                }
                if (!$mapped) continue;
                $this->createEmpleadoFromRow($mapped);
                $count++;
            }
        } else {
            $path = $this->importFile->store('temp/imports');
            $full = storage_path('app/' . $path);
            if (!file_exists($full)) {
                session()->flash('error', 'Archivo no encontrado para importar.');
                return;
            }
            $handle = fopen($full, 'r');
            $header = fgetcsv($handle);
            $header = array_map('strtolower', array_map('trim', $header ?: []));
            while (($row = fgetcsv($handle)) !== false) {
                $mapped = array_combine($header, $row);
                if (!$mapped) continue;
                $this->createEmpleadoFromRow($mapped);
                $count++;
            }
            fclose($handle);
            @unlink($full);
        }
        session()->flash('message', "Importación completada: {$count} empleados cargados.");
        $this->reset('importFile');
    }
    
    private function sanitizeMetodo($m): ?string
    {
        $m = strtolower(trim((string)$m));
        $map = [
            'efectivo' => 'efectivo','cash' => 'efectivo','ef' => 'efectivo',
            'transferencia' => 'transferencia','trans' => 'transferencia','wire' => 'transferencia',
            'tarjeta' => 'tarjeta','card' => 'tarjeta','credito' => 'tarjeta','debito' => 'tarjeta'
        ];
        return $map[$m] ?? null;
    }
    
    private function normalizePhone($p): ?string
    {
        $digits = preg_replace('/\D+/', '', (string)$p);
        if (!$digits) return null;
        if (str_starts_with($digits, '0') && strlen($digits) >= 11) {
            $digits = '58' . substr($digits, 1);
        } elseif (strlen($digits) === 10 && !str_starts_with($digits, '58')) {
            $digits = '58' . $digits;
        }
        return $digits;
    }
    
    private function num($v, $min = 0): float
    {
        $n = (float)str_replace(',', '.', (string)$v);
        if ($n < $min) $n = $min;
        return round($n, 2);
    }
    
    private function createEmpleadoFromRow(array $data): void
    {
        \App\Models\Empleado::create([
            'empresa_id' => auth()->user()->empresa_id,
            'sucursal_id' => auth()->user()->sucursal_id,
            'nombre' => $data['nombre'] ?? '',
            'apellido' => $data['apellido'] ?? null,
            'documento' => $data['documento'] ?? null,
            'puesto' => $data['puesto'] ?? null,
            'salario_base' => $this->num($data['salario'] ?? 0),
            'horas_extra_base' => $this->num($data['horas_extra'] ?? 0),
            'bono_fijo' => $this->num($data['bono'] ?? 0),
            'comision_fija' => $this->num($data['comision'] ?? 0),
            'metodo_pago' => $this->sanitizeMetodo($data['metodo'] ?? ''),
            'telefono' => $this->normalizePhone($data['telefono'] ?? ''),
            'email' => $data['email'] ?? null,
            'activo' => isset($data['activo']) ? (bool)$data['activo'] : true
        ]);
    }

    public function toggleActivo($id)
    {
        $emp = Empleado::findOrFail($id);
        $emp->update(['activo' => !$emp->activo]);
    }

    public function getExportQuery()
    {
        $query = Empleado::query();
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->search . '%')
                  ->orWhere('apellido', 'like', '%' . $this->search . '%')
                  ->orWhere('documento', 'like', '%' . $this->search . '%');
            });
        }
        if ($this->activo !== '') {
            $query->where('activo', $this->activo === 'activo');
        }
        return $query->orderBy($this->sortBy, $this->sortDirection);
    }

    public function getExportHeaders()
    {
        return ['Nombre', 'Documento', 'Puesto', 'Salario', 'Método', 'Estado'];
    }

    public function formatExportRow($emp)
    {
        return [
            $emp->nombre . ' ' . ($emp->apellido ?? ''),
            $emp->documento ?? '',
            $emp->puesto ?? '',
            number_format($emp->salario_base, 2),
            $emp->metodo_pago ?? '',
            $emp->activo ? 'Activo' : 'Inactivo'
        ];
    }

    public function render()
    {
        $empleados = $this->getExportQuery()->paginate($this->perPage);
        return $this->renderWithLayout('livewire.admin.empleados.index', compact('empleados'), [
            'description' => 'Gestión de '
        ]);
    }
}
