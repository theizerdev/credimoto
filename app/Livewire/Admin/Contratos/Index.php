<?php

namespace App\Livewire\Admin\Contratos;

use App\Traits\HasDynamicLayout;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Traits\Exportable;

class Index extends Component
{
    use WithPagination, Exportable, HasDynamicLayout;

    public $search = '';
    public $estado = '';
    public $empresa_id = '';
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    public $perPage = 10;
    public $showDeleted = false; // Variable para controlar qué pestaña mostrar

    protected $queryString = [
        'search' => ['except' => ''],
        'estado' => ['except' => ''],
        'empresa_id' => ['except' => ''],
        'sortBy' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
        'perPage' => ['except' => 10],
        'showDeleted' => ['except' => false]
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'estado', 'empresa_id', 'sortBy', 'sortDirection', 'perPage', 'showDeleted']);
    }

    public function sort($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function setShowDeleted($show)
    {
        $this->showDeleted = $show;
        // Resetear la página cuando cambiamos de pestaña
        $this->resetPage();
    }

    public function restore($id)
    {
        $contrato = Contrato::withTrashed()->findOrFail($id);
        $contrato->restore();
        session()->flash('message', 'Contrato restaurado correctamente.');
    }
    public function delete($id)
    {
        $contrato = Contrato::withTrashed()->findOrFail($id);
        $contrato->delete();
        session()->flash('message', 'Contrato restaurado correctamente.');
    }

    public function toggleContractStatus($id)
    {
        $contrato = Contrato::findOrFail($id);
        
        // Toggle between active/inactive states
        // Only allow toggle between 'activo' and 'cancelado' for now
        if ($contrato->estado === 'activo') {
            $contrato->estado = 'cancelado';
        } else {
            $contrato->estado = 'activo';
        }
        
        $contrato->save();
        
        session()->flash('message', 'Estado del contrato actualizado correctamente.');
    }

    private function buildBaseQuery()
    {
        if ($this->showDeleted) {
            // Para contratos eliminados, también incluimos clientes eliminados
            $query = Contrato::onlyTrashed()
                ->with(['cliente' => function($query) {
                    $query->withTrashed(); // Incluir clientes eliminados también
                }, 'unidad.moto', 'empresa']);
        } else {
            $query = Contrato::query()->with(['cliente', 'unidad.moto', 'empresa']); // Contratos regulares
            
            if ($this->estado !== '') {
                $query->where('estado', $this->estado);
            }

            if ($this->empresa_id) {
                $query->where('empresa_id', $this->empresa_id);
            }
        }

        if (auth()->check() && auth()->user()->cliente_id) {
            $query->where('cliente_id', auth()->user()->cliente_id);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('numero_contrato', 'like', '%' . $this->search . '%')
                  ->orWhereHas('cliente', function($q) {
                      $q->where('nombre', 'like', '%' . $this->search . '%')
                        ->orWhere('apellido', 'like', '%' . $this->search . '%')
                        ->orWhere('documento', 'like', '%' . $this->search . '%');
                  })
                  ->orWhereHas('unidad', function($q) {
                      $q->where('placa', 'like', '%' . $this->search . '%')
                        ->orWhere('vin', 'like', '%' . $this->search . '%');
                  });
            });
        }

        return $query;
    }

    public function getExportQuery()
    {
        $query = $this->buildBaseQuery();

        return $query->orderBy($this->sortBy, $this->sortDirection);
    }

    public function getExportHeaders(): array
    {
        return [
            'ID',
            'N° Contrato',
            'Cliente',
            'Documento',
            'Moto',
            'Placa',
            'Monto Financiado',
            'Saldo Pendiente',
            'Estado',
            'Empresa',
            'Fecha Inicio'
        ];
    }

    public function formatExportRow($row): array
    {
        return [
            $row->id,
            $row->numero_contrato,
            $row->cliente->nombre_completo ?? 'N/A',
            $row->cliente->documento ?? 'N/A',
            $row->unidad->moto->titulo ?? 'N/A',
            $row->unidad->placa ?? 'S/P',
            $row->monto_financiado,
            $row->saldo_pendiente,
            ucfirst($row->estado),
            $row->empresa->razon_social ?? 'N/A',
            $row->fecha_inicio->format('d/m/Y')
        ];
    }

    public function render()
    {
        $query = $this->buildBaseQuery();

        $contratos = $query->orderBy($this->sortBy, $this->sortDirection)
            ->paginate($this->perPage);

        $empresas = Empresa::all();
        
        // Estadísticas para contratos normales
        $statsQuery = Contrato::query();
        if (auth()->check() && auth()->user()->cliente_id) {
            $statsQuery->where('cliente_id', auth()->user()->cliente_id);
        }
        $totalContratos = (clone $statsQuery)->count();
        $contratosActivos = (clone $statsQuery)->whereIn('estado', ['activo', 'mora'])->count();
        $contratosMora = (clone $statsQuery)->where('estado', 'mora')->count();
        $contratosCompletados = (clone $statsQuery)->where('estado', 'completado')->count();
        
        // Estadística específica para contratos eliminados
        $contratosEliminados = Contrato::onlyTrashed()->count();

        return view('livewire.admin.contratos.index', [
            'contratos' => $contratos,
            'empresas' => $empresas,
            'totalContratos' => $totalContratos,
            'contratosActivos' => $contratosActivos,
            'contratosMora' => $contratosMora,
            'contratosCompletados' => $contratosCompletados,
            'contratosEliminados' => $contratosEliminados
        ])->layout($this->getLayout());
    }
}