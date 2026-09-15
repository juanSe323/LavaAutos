<template>
  <div class="d-flex" style="min-height: 100vh; background-color: #f5f6fa;">
    <!-- Sidebar -->
    <nav class="bg-white border-end p-3 d-flex flex-column" style="width: 220px; flex-shrink: 0;">
      <div class="d-flex align-items-center gap-2 mb-4 px-2">
        <span style="font-size: 1.4rem;">🚗</span>
        <span class="fw-bold">LavaAutos</span>
      </div>

      <ul class="nav flex-column gap-1">
        <li class="nav-item" v-for="item in menuItems" :key="item.id">
          <a
            href="#"
            class="nav-link d-flex align-items-center gap-2 rounded px-2 py-2"
            :class="vistaActual === item.id ? 'bg-primary bg-opacity-10 text-primary fw-semibold' : 'text-dark'"
            @click.prevent="$emit('cambiar-vista', item.id)"
          >
            <span>{{ item.icono }}</span> {{ item.label }}
          </a>
        </li>
      </ul>

      <div class="mt-auto pt-4">
        <a href="#" class="nav-link d-flex align-items-center gap-2 text-danger px-2" @click.prevent="$emit('cerrar-sesion')">
          <span>🚪</span> Cerrar Sesión
        </a>
      </div>
    </nav>

    <!-- Columna derecha -->
    <div class="flex-grow-1 d-flex flex-column">
      <!-- Topbar -->
      <header class="bg-white border-bottom d-flex align-items-center justify-content-between px-4 py-2">
        <input type="text" class="form-control" style="max-width: 300px;" placeholder="🔍 Buscar...">
        <div class="d-flex align-items-center gap-3">
          <span style="font-size: 1.2rem;">🔔</span>
          <div class="text-end">
            <div class="fw-semibold small">{{ usuario.nombre }}</div>
            <div class="text-muted" style="font-size: 0.75rem;">Administrador</div>
          </div>
          <div
            class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center"
            style="width: 36px; height: 36px;"
          >
            {{ inicial }}
          </div>
        </div>
      </header>

      <!-- Vista actual -->
      <main class="flex-grow-1 p-4">
        <div v-if="vistaActual === 'servicios'">
          <TiposServicio />
          <hr class="my-4">
          <ColaServicios />
        </div>
        <div v-else class="card shadow-sm p-4 border-0">
          <h5 class="card-title mb-2">{{ tituloVistaActual }}</h5>
          <p class="card-text text-muted mb-0">
            Módulo en construcción — aquí irá la funcionalidad real de "{{ tituloVistaActual }}".
          </p>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import TiposServicio from './admin/TiposServicio.vue'
import ColaServicios from './admin/ColaServicios.vue'

const props = defineProps({
  usuario: { type: Object, required: true },
  vistaActual: { type: String, required: true }
})
defineEmits(['cambiar-vista', 'cerrar-sesion'])

// Mismos 6 módulos del sidebar que aparecen en los mockups de la propuesta.
const menuItems = [
  { id: 'dashboard', label: 'Dashboard', icono: '📊' },
  { id: 'servicios', label: 'Servicios', icono: '🚿' },
  { id: 'empleados', label: 'Empleados', icono: '👥' },
  { id: 'convenios', label: 'Convenios', icono: '🤝' },
  { id: 'inventario', label: 'Inventario', icono: '📦' },
  { id: 'reportes', label: 'Reportes', icono: '📈' }
]

const tituloVistaActual = computed(() => {
  const item = menuItems.find(i => i.id === props.vistaActual)
  return item ? item.label : ''
})

const inicial = computed(() => props.usuario.nombre?.charAt(0).toUpperCase() || 'A')
</script>