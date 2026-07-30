<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Dashboard;
use App\Livewire\Admin\Empresas\Index as EmpresasIndex;
use App\Livewire\Admin\Empresas\Create as EmpresasCreate;
use App\Livewire\Admin\Empresas\Edit as EmpresasEdit;
use App\Livewire\Admin\Sucursales\Index as SucursalesIndex;
use App\Livewire\Admin\Sucursales\Create as SucursalesCreate;
use App\Livewire\Admin\Sucursales\Edit as SucursalesEdit;
use App\Livewire\Admin\Sucursales\Show as SucursalesShow;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Admin\Users\Create as UsersCreate;
use App\Livewire\Admin\Users\Edit as UsersEdit;
use App\Livewire\Admin\Roles\Index as RolesIndex;
use App\Livewire\Admin\Roles\Create as RolesCreate;
use App\Livewire\Admin\Roles\Edit as RolesEdit;
use App\Livewire\Admin\Roles\Show as RolesShow;
use App\Livewire\Admin\Permissions\Index as PermissionsIndex;
use App\Livewire\Admin\Permissions\Create as PermissionsCreate;
use App\Livewire\Admin\Permissions\Edit as PermissionsEdit;
use App\Livewire\Admin\ActiveSessions;
use App\Livewire\Admin\ConceptosPago\Index as ConceptosPagoIndex;
use App\Livewire\Admin\ConceptosPago\Create as ConceptosPagoCreate;
use App\Livewire\Admin\ConceptosPago\Edit as ConceptosPagoEdit;
use App\Livewire\Admin\Motos\Index as MotosIndex;
use App\Livewire\Admin\Motos\Create as MotosCreate;
use App\Livewire\Admin\Motos\Edit as MotosEdit;
use App\Livewire\Admin\Clientes\Index as ClientesIndex;
use App\Livewire\Admin\Clientes\Create as ClientesCreate;
use App\Livewire\Admin\Clientes\Edit as ClientesEdit;
use App\Livewire\Admin\Contratos\Index as ContratosIndex;
use App\Livewire\Admin\Contratos\Create as ContratosCreate;
use App\Livewire\Admin\Contratos\Edit as ContratosEdit;
use App\Livewire\Admin\Contratos\Show as ContratosShow;
use App\Livewire\Admin\Pagos\Register as PagosRegister;



// Empresas
Route::get('/empresas', EmpresasIndex::class)->name('empresas.index');
Route::get('/empresas/crear', EmpresasCreate::class)->name('empresas.create');
Route::get('/empresas/{empresa}/editar', EmpresasEdit::class)->name('empresas.edit');

// Países
Route::get('/paises', \App\Livewire\Admin\Paises\PaisIndex::class)->name('paises.index');
Route::get('/paises/crear', \App\Livewire\Admin\Paises\Create::class)->name('paises.create');
Route::get('/paises/{pais}/editar', \App\Livewire\Admin\Paises\Edit::class)->name('paises.edit');

// Sucursales
Route::get('/sucursales', SucursalesIndex::class)->name('sucursales.index');
Route::get('/sucursales/crear', SucursalesCreate::class)->name('sucursales.create');
Route::get('/sucursales/{sucursal}/editar', SucursalesEdit::class)->name('sucursales.edit');
Route::get('/sucursales/{sucursal}', SucursalesShow::class)->name('sucursales.show');

// Usuarios
Route::get('/usuarios', UsersIndex::class)->name('users.index');
Route::get('/usuarios/crear', UsersCreate::class)->name('users.create');
Route::get('/usuarios/{user}/editar', UsersEdit::class)->name('users.edit');
 // Perfil de usuario
Route::prefix('profile')->group(function () {
    Route::get('/', \App\Livewire\Admin\Users\Profile\Index::class)->name('users.profile');
    Route::get('/{user_id}/password', \App\Livewire\Admin\Users\Profile\ChangePassword::class)->name('users.password');
    Route::get('/{user_id}/history', \App\Livewire\Admin\Users\Profile\HistoryUser::class)->name('users.history');
});

// Roles
Route::get('/roles', RolesIndex::class)->name('roles.index');
Route::get('/roles/crear', RolesCreate::class)->name('roles.create');
Route::get('/roles/{role}/editar', RolesEdit::class)->name('roles.edit');
Route::get('/roles/{role}', RolesShow::class)->name('roles.show');

// Permisos
Route::get('/permisos', PermissionsIndex::class)->name('permissions.index');
Route::get('/permisos/crear', PermissionsCreate::class)->name('permissions.create');
Route::get('/permisos/{permission}/editar', PermissionsEdit::class)->name('permissions.edit');


// Sesiones activas
Route::get('/active-sessions', ActiveSessions::class)->name('active-sessions.index');

// Monitoreo
Route::prefix('monitoreo')->as('monitoreo.')->group(function () {
    Route::get('/servidor', \App\Livewire\Admin\Monitoreo\Servidor::class)->name('servidor');
    Route::get('/base-datos', \App\Livewire\Admin\Monitoreo\BaseDatos::class)->name('base-datos');
    Route::get('/estudiantes', \App\Livewire\Admin\Monitoreo\Estudiantes::class)->name('estudiantes');
    Route::get('/accesos', \App\Livewire\Admin\Monitoreo\Accesos::class)->name('accesos');
});

// Tasas de Cambio
Route::get('/tasas-cambio', \App\Livewire\Admin\ExchangeRates::class)->name('exchange-rates');



// Series de Documentos
Route::get('/series', \App\Livewire\Admin\Series\Index::class)->name('series.index');
Route::get('/series/crear', \App\Livewire\Admin\Series\Create::class)->name('series.create');
Route::get('/series/{serie}/editar', \App\Livewire\Admin\Series\Edit::class)->name('series.edit');


// Pagos
Route::get('/pagos', \App\Livewire\Admin\Pagos\Index::class)->name('pagos.index');
Route::get('/pagos/crear', \App\Livewire\Admin\Pagos\Create::class)->name('pagos.create');
Route::get('/pagos/{pago}/editar', \App\Livewire\Admin\Pagos\Edit::class)->name('pagos.edit');
Route::get('/pagos/{pago}', \App\Livewire\Admin\Pagos\Show::class)->name('pagos.show');
Route::get('/pagos/{pago}/print', [\App\Livewire\Admin\Pagos\Index::class, 'downloadReceipt'])->name('pagos.print');
Route::get('/pagos/{pago}/ticket', [\App\Livewire\Admin\Pagos\Index::class, 'ticketView'])->name('pagos.ticket');
Route::get('/pagos/{pago}/ticket-thermal', [\App\Livewire\Admin\Pagos\Index::class, 'ticketThermalView'])->name('pagos.ticket-thermal');
Route::get('/pagos/comprobante/{comprobante}', \App\Livewire\Admin\Pagos\Comprobantes::class)->name('pagos.comprobante');

// Motos
Route::get('/motos', MotosIndex::class)->name('motos.index');
Route::get('/motos/crear', MotosCreate::class)->name('motos.create');
Route::get('/motos/{moto}/editar', MotosEdit::class)->name('motos.edit');
Route::get('/motos/{moto}/detalles', \App\Livewire\Admin\Motos\Details::class)->name('motos.details');

// Unidades (Inventario)
Route::get('/motos/{moto}/unidades', \App\Livewire\Admin\Motos\Unidades\Index::class)->name('motos.unidades.index');
Route::get('/motos/{moto}/unidades/crear', \App\Livewire\Admin\Motos\Unidades\Create::class)->name('motos.unidades.create');

// Unidades (Inventario General)
Route::get('/inventario/unidades', \App\Livewire\Admin\Inventario\Unidades\Index::class)->name('inventario.unidades.index');
Route::get('/inventario/unidades/crear', \App\Livewire\Admin\Inventario\Unidades\Create::class)->name('inventario.unidades.create');
Route::get('/inventario/unidades/{unidad}', \App\Livewire\Admin\Inventario\Unidades\Show::class)->name('inventario.unidades.show');
Route::get('/inventario/unidades/{unidad}/historial', \App\Livewire\Admin\Inventario\Unidades\History::class)->name('inventario.unidades.history');

// Clientes
Route::get('/clientes', ClientesIndex::class)->name('clientes.index');
Route::get('/clientes/crear', ClientesCreate::class)->name('clientes.create');
Route::get('/clientes/{cliente}', \App\Livewire\Admin\Clientes\Show::class)->name('clientes.show');
Route::get('/clientes/{cliente}/editar', ClientesEdit::class)->name('clientes.edit');

// Contratos
Route::get('/contratos', ContratosIndex::class)->name('contratos.index');
Route::get('/contratos/crear', ContratosCreate::class)->name('contratos.create');
Route::get('/contratos/{contrato}', ContratosShow::class)->name('contratos.show');
Route::get('/contratos/{contrato}/editar', ContratosEdit::class)->name('contratos.edit');
Route::get('/contratos/{contrato}/pagar', PagosRegister::class)->name('contratos.pagar');

// Registro de Actividad

 
Route::get('/activity-log', \App\Livewire\Admin\ActivityLog::class)->name('activity-log');


// Cajas
Route::get('/cajas', \App\Livewire\Admin\Cajas\Index::class)->name('cajas.index');
Route::get('/cajas/crear', \App\Livewire\Admin\Cajas\Create::class)->name('cajas.create');
Route::get('/cajas/{caja}', \App\Livewire\Admin\Cajas\Show::class)->name('cajas.show');
Route::get('/cajas/{caja}/export', [\App\Http\Controllers\Admin\CajaExportController::class, 'export'])->name('cajas.export');

// Reglas de Morosidad
Route::get('/reglas-morosidad', \App\Livewire\Admin\LatePaymentRules\Index::class)->name('late-payment-rules.index');

// Notificaciones
Route::get('/notifications', \App\Livewire\Admin\Notifications\Index::class)->name('notifications.index');

// WhatsApp
Route::prefix('whatsapp')->as('whatsapp.')->group(function () {
    Route::get('/', \App\Livewire\Admin\Whatsapp\Index::class)->name('index');
    Route::get('/dashboard', \App\Livewire\Admin\Whatsapp\Index::class)->name('dashboard');
});

// Exportador de Base de Datos
Route::get('/exportar-base-datos', \App\Livewire\Admin\DatabaseExport::class)->name('database-export');

// WhatsApp
Route::get('/whatsapp', \App\Livewire\Admin\Whatsapp\Index::class)->name('whatsapp.index');

// Conceptos de Pago
Route::get('/conceptos-pago', ConceptosPagoIndex::class)->name('conceptos-pago.index');
Route::get('/conceptos-pago/crear', ConceptosPagoCreate::class)->name('conceptos-pago.create');
Route::get('/conceptos-pago/{concepto}/editar', ConceptosPagoEdit::class)->name('conceptos-pago.edit');

// Reportes
Route::get('/reportes/estado-cuenta', \App\Livewire\Admin\Reportes\EstadoCuenta::class)->name('reportes.estado-cuenta');

// Nómina
Route::get('/nomina/procesar', \App\Livewire\Admin\Nomina\Procesar::class)->name('nomina.procesar');

// Sorteos
Route::get('/sorteo', \App\Livewire\Admin\Sorteo\Index::class)->name('sorteo.index');
Route::get('/sorteo/{sorteo}', \App\Livewire\Admin\Sorteo\Detalle::class)->name('sorteo.detalle');

// Empleados
Route::get('/empleados', \App\Livewire\Admin\Empleados\Index::class)->name('empleados.index');
Route::get('/empleados/crear', \App\Livewire\Admin\Empleados\Create::class)->name('empleados.create');
Route::get('/empleados/{empleado}/editar', \App\Livewire\Admin\Empleados\Edit::class)->name('empleados.edit');
