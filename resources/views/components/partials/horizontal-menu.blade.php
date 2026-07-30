<div class="app-brand demo me-4">
  <a href="{{ url('/') }}" class="app-brand-link">
    <span class="app-brand-logo demo">
      <span class="text-primary">
        <svg width="32" height="18" viewBox="0 0 38 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path
            d="M30.0944 2.22569C29.0511 0.444187 26.7508 -0.172113 24.9566 0.849138C23.1623 1.87039 22.5536 4.14247 23.5969 5.92397L30.5368 17.7743C31.5801 19.5558 33.8804 20.1721 35.6746 19.1509C37.4689 18.1296 38.0776 15.8575 37.0343 14.076L30.0944 2.22569Z"
            fill="currentColor" />
          <path
            d="M30.171 2.22569C29.1277 0.444187 26.8274 -0.172113 25.0332 0.849138C23.2389 1.87039 22.6302 4.14247 23.6735 5.92397L30.6134 17.7743C31.6567 19.5558 33.957 20.1721 35.7512 19.1509C37.5455 18.1296 38.1542 15.8575 37.1109 14.076L30.171 2.22569Z"
            fill="url(#paint0_linear_2989_100980)"
            fill-opacity="0.4" />
          <path
            d="M22.9676 2.22569C24.0109 0.444187 26.3112 -0.172113 28.1054 0.849138C29.8996 1.87039 30.5084 4.14247 29.4651 5.92397L22.5251 17.7743C21.4818 19.5558 19.1816 20.1721 17.3873 19.1509C15.5931 18.1296 14.9843 15.8575 16.0276 14.076L22.9676 2.22569Z"
            fill="currentColor" />
          <path
            d="M14.9558 2.22569C13.9125 0.444187 11.6122 -0.172113 9.818 0.849138C8.02377 1.87039 7.41502 4.14247 8.45833 5.92397L15.3983 17.7743C16.4416 19.5558 18.7418 20.1721 20.5361 19.1509C22.3303 18.1296 22.9391 15.8575 21.8958 14.076L14.9558 2.22569Z"
            fill="currentColor" />
          <path
            d="M14.9558 2.22569C13.9125 0.444187 11.6122 -0.172113 9.818 0.849138C8.02377 1.87039 7.41502 4.14247 8.45833 5.92397L15.3983 17.7743C16.4416 19.5558 18.7418 20.1721 20.5361 19.1509C22.3303 18.1296 22.9391 15.8575 21.8958 14.076L14.9558 2.22569Z"
            fill="url(#paint1_linear_2989_100980)"
            fill-opacity="0.4" />
          <path
            d="M7.82901 2.22569C8.87231 0.444187 11.1726 -0.172113 12.9668 0.849138C14.7611 1.87039 15.3698 4.14247 14.3265 5.92397L7.38656 17.7743C6.34325 19.5558 4.04298 20.1721 2.24875 19.1509C0.454514 18.1296 -0.154233 15.8575 0.88907 14.076L7.82901 2.22569Z"
            fill="currentColor" />
          <defs>
            <linearGradient
              id="paint0_linear_2989_100980"
              x1="5.36642"
              y1="0.849138"
              x2="10.532"
              y2="24.104"
              gradientUnits="userSpaceOnUse">
              <stop offset="0" stop-opacity="1" />
              <stop offset="1" stop-opacity="0" />
            </linearGradient>
            <linearGradient
              id="paint1_linear_2989_100980"
              x1="5.19475"
              y1="0.849139"
              x2="10.3357"
              y2="24.1155"
              gradientUnits="userSpaceOnUse">
              <stop offset="0" stop-opacity="1" />
              <stop offset="1" stop-opacity="0" />
            </linearGradient>
          </defs>
        </svg>
      </span>
    </span>
    <span class="app-brand-text demo menu-text fw-semibold ms-2">{{ config('app.name', 'Laravel') }}</span>
  </a>
</div>

<ul class="menu-inner py-1">
   <!-- Dashboard -->
    <li class="menu-item {{ request()->routeIs('admin/dashboard') || request()->routeIs('superadmin/dashboard') ? 'active' : '' }}">
      <a href="{{ url('/') }}" class="menu-link">
        <i class="menu-icon tf-icons ri ri-home-4-line"></i>
        <div>Dashboard</div>
      </a>
    </li>

    @can('access nomina')
    <!-- Nómina 
    <li class="menu-item {{ request()->routeIs('admin.nomina.*') ? 'active' : '' }}">
      <a href="{{ route('admin.nomina.procesar') }}" class="menu-link">
        <i class="menu-icon tf-icons ri ri-briefcase-3-line"></i>
        <div>Nómina</div>
      </a>
    </li>
    -->
    @endcan
    @can('access empleados')
    <!--
    <li class="menu-item {{ request()->routeIs('admin.empleados.*') ? 'active' : '' }}">
      <a href="{{ route('admin.empleados.index') }}" class="menu-link">
        <i class="menu-icon tf-icons ri ri-team-line"></i>
        <div>Empleados</div>
      </a>
    </li>
    -->
    @endcan

     @can('access motos')
        <li class="menu-item {{ request()->routeIs('admin.motos.index') ? 'active' : '' }}">
          <a href="{{ route('admin.motos.index') }}" class="menu-link">
            <i class="menu-icon tf-icons ri ri-motorbike-line"></i>
            <div>Inventario Motos</div>
          </a>
        </li>
        @endcan
        @can('access moto unidades')
        <li class="menu-item {{ request()->routeIs('admin.inventario.unidades.index') ? 'active' : '' }}">
          <a href="{{ route('admin.inventario.unidades.index') }}" class="menu-link">
            <i class="menu-icon tf-icons ri ri-car-line"></i>
            <div>Inventario Unidades</div>
          </a>
        </li>
        @endcan
        @can('access clientes')
        <li class="menu-item {{ request()->routeIs('admin.clientes.index') ? 'active' : '' }}">
          <a href="{{ route('admin.clientes.index') }}" class="menu-link">
            <i class="menu-icon tf-icons ri ri-user-line"></i>
            <div>Clientes</div>
          </a>
        </li>
        @endcan
          @can('access contratos')
        <li class="menu-item {{ request()->routeIs('admin.contratos.index') ? 'active' : '' }}">
          <a href="{{ route('admin.contratos.index') }}" class="menu-link">
            <i class="menu-icon tf-icons ri ri-file-list-3-line"></i>
            <div>Contratos</div>
          </a>
        </li>
        @endcan

   
        @canany(['access pagos','access contratos'])
    <!-- Reportes -->
    <li class="menu-item {{ request()->routeIs('admin.reportes.estado-cuenta') ? 'active' : '' }}">
      <a href="{{ route('admin.reportes.estado-cuenta') }}" class="menu-link">
        <i class="menu-icon tf-icons ri ri-file-chart-line"></i>
        <div>Estado de Cuenta</div>
      </a>
    </li>
    @endcan
    
    <!-- Dashboard -->
    <li class="menu-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
      <a href="{{ route('admin.dashboard') }}" class="menu-link">
        <i class="menu-icon tf-icons ri ri-dashboard-line"></i>
        <div>Dashboard</div>
      </a>
    </li>

    @canany(['access conceptos pago', 'access cajas', 'access pagos'])
    <!-- Pagos y Finanzas -->
    <li class="menu-item {{ request()->routeIs('admin.pagos.*') || request()->routeIs('admin.conceptos-pago.*') || request()->routeIs('admin.cajas.*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons ri ri-money-dollar-circle-line"></i>
        <div>Pagos y Finanzas</div>
      </a>
      <ul class="menu-sub">
       
        @can('access conceptos pago')
        <li class="menu-item {{ request()->routeIs('admin.conceptos-pago.*') ? 'active' : '' }}">
          <a href="{{ route('admin.conceptos-pago.index') }}" class="menu-link">
            <div>Concepto de Pagos</div>
          </a>
        </li>
        @endcan
        @can('access pagos')
        <li class="menu-item {{ request()->routeIs('admin.pagos.*') ? 'active' : '' }}">
          <a href="{{ route('admin.pagos.index') }}" class="menu-link">
            <div>Registro de Pagos</div>
          </a>
        </li>
        @endcan
        @can('access cajas')
        <li class="menu-item {{ request()->routeIs('admin.cajas.*') ? 'active' : '' }}">
          <a href="{{ route('admin.cajas.index') }}" class="menu-link">
            <div>Caja Chica</div>
          </a>
        </li>
        @endcan
      </ul>
    </li>
    @endcan

    <!-- Sorteos 
    <li class="menu-item {{ request()->routeIs('admin.sorteo.*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons ri ri-gift-line"></i>
        <div>Sorteos</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item">
          <a href="{{ route('sorteo.app') }}" class="menu-link" target="_blank">
            <div>Realizar Sorteo</div>
          </a>
        </li>
        <li class="menu-item {{ request()->routeIs('admin.sorteo.index') ? 'active' : '' }}">
          <a href="{{ route('admin.sorteo.index') }}" class="menu-link">
            <div>Administración</div>
          </a>
        </li>
      </ul>
    </li>
    -->
    @canany(['access empresas', 'access sucursales', 'access school periods', 'access niveles educativos', 'access turnos'])
    <!-- Configuración Institucional -->
    <li class="menu-item {{ request()->routeIs('admin.empresas.*') || request()->routeIs('admin.sucursales.*') || request()->routeIs('admin.school-periods.*') || request()->routeIs('admin.niveles-educativos.*') || request()->routeIs('admin.turnos.*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons ri ri-building-4-line"></i>
        <div>Configuración</div>
      </a>
      <ul class="menu-sub">
        @can('access empresas')
        <li class="menu-item {{ request()->routeIs('admin.empresas.index') ? 'active' : '' }}">
          <a href="{{ route('admin.empresas.index') }}" class="menu-link">
            <div>Empresas</div>
          </a>
        </li>
        @endcan
        @can('access paises')
        <li class="menu-item {{ request()->routeIs('admin.paises.index') ? 'active' : '' }}">
          <a href="{{ route('admin.paises.index') }}" class="menu-link">
            <div>Países</div>
          </a>
        </li>
        @endcan
        @can('access sucursales')
        <li class="menu-item {{ request()->routeIs('admin.sucursales.index') ? 'active' : '' }}">
          <a href="{{ route('admin.sucursales.index') }}" class="menu-link">
            <div>Sucursales</div>
          </a>
        </li>
        @endcan
       
      </ul>
    </li>
    @endcan

    @can('access series')
    <!-- Series de Documentos -->
    <li class="menu-item {{ request()->routeIs('admin.series.*') ? 'active' : '' }}">
      <a href="{{ route('admin.series.index') }}" class="menu-link">
        <i class="menu-icon tf-icons ri ri-file-list-3-line"></i>
        <div>Series</div>
      </a>
    </li>
    @endcan

    @can('view exchange-rates')
    <!-- Tasas de Cambio -->
    <li class="menu-item {{ request()->routeIs('admin.exchange-rates') ? 'active' : '' }}">
      <a href="{{ route('admin.exchange-rates') }}" class="menu-link">
        <i class="menu-icon tf-icons ri ri-exchange-dollar-line"></i>
        <div>Tasas BCV</div>
      </a>
    </li>
    @endcan

    <!-- Personalización de Plantilla -->
    <li class="menu-item {{ request()->routeIs('admin.template-customization') ? 'active' : '' }}">
      <a href="{{ route('admin.template-customization') }}" class="menu-link">
        <i class="menu-icon tf-icons ri ri-palette-line"></i>
        <div>Personalización</div>
      </a>
    </li>




   
 @can('access whatsapp')
    <!-- WhatsApp -->
    <li class="menu-item {{ request()->routeIs('admin.whatsapp.*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons ri ri-whatsapp-line"></i>
        <div>WhatsApp</div>
      </a>
      <ul class="menu-sub">
        @can('access whatsapp')
        <li class="menu-item {{ request()->routeIs('admin.whatsapp.index') ? 'active' : '' }}">
          <a href="{{ route('admin.whatsapp.index') }}" class="menu-link">
            <div>Panel</div>
          </a>
        </li>
        @endcan
        
      </ul>
    </li>
   @endcan


   @canany(['access users', 'access roles', 'access permissions'])
    <!-- Usuarios y Permisos -->
    <li class="menu-item {{ request()->routeIs('admin.users.*') || request()->routeIs('admin.roles.*') || request()->routeIs('admin.permissions.*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons ri ri-group-line"></i>
        <div>Administración</div>
      </a>
      <ul class="menu-sub">
        @can('access users')
        <li class="menu-item {{ request()->routeIs('admin.users.index') ? 'active' : '' }}">
          <a href="{{ route('admin.users.index') }}" class="menu-link">
            <div>Usuarios</div>
          </a>
        </li>
        @endcan
        @can('access roles')
        <li class="menu-item {{ request()->routeIs('admin.roles.index') ? 'active' : '' }}">
          <a href="{{ route('admin.roles.index') }}" class="menu-link">
            <div>Roles</div>
          </a>
        </li>
        @endcan
        @can('access permissions')
        <li class="menu-item {{ request()->routeIs('admin.permissions.index') ? 'active' : '' }}">
          <a href="{{ route('admin.permissions.index') }}" class="menu-link">
            <div>Permisos</div>
          </a>
        </li>
        @endcan
      </ul>
    </li>
    @endcan

    @canany(['view active sessions', 'access activity log', 'access monitoreo'])
    <!-- Sistema y Monitoreo -->
    <li class="menu-item {{ request()->routeIs('admin.active-sessions*') || request()->is('admin/activity-log*') || request()->routeIs('admin.monitoreo.*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons ri ri-line-chart-line"></i>
        <div>Monitoreo</div>
      </a>
      <ul class="menu-sub">
        @can('view active sessions')
        <li class="menu-item {{ request()->routeIs('admin.active-sessions*') ? 'active' : '' }}">
          <a href="{{ route('admin.active-sessions.index') }}" class="menu-link">
            <div>Sesiones</div>
          </a>
        </li>
        @endcan
        @can('access activity log')
        <li class="menu-item {{ request()->is('admin/activity-log*') ? 'active' : '' }}">
          <a href="{{ route('admin.activity-log') }}" class="menu-link">
            <div>Actividad</div>
          </a>
        </li>
        @endcan
        @can('access database export')
        <li class="menu-item {{ request()->routeIs('admin.database-export') ? 'active' : '' }}">
          <a href="{{ route('admin.database-export') }}" class="menu-link">
            <div>Exportar Base de Datos</div>
          </a>
        </li>
        @endcan

      </ul>
    </li>
    @endcan
</ul>
