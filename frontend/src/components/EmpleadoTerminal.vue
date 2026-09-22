<template>
  <div style="min-height: 100vh; background-color: #f5f6fa;">
    <header class="d-flex justify-content-between align-items-center px-4 py-3 bg-white border-bottom">
      <div class="d-flex align-items-center gap-2">
        <span style="font-size: 1.4rem;">🚗</span>
        <span class="fw-bold">LavaAutos</span>
      </div>
      <button class="btn btn-outline-secondary btn-sm" @click="$emit('cerrar-sesion')">Cerrar Sesión</button>
    </header>

    <main class="p-4">
      <h4 class="mb-1">Nuevo Servicio de Lavado</h4>
      <p class="text-muted small mb-4">Complete los detalles para registrar el ingreso del vehículo</p>

      <div v-if="mensaje" class="alert py-2" :class="mensajeTipo === 'error' ? 'alert-danger' : 'alert-success'">
        {{ mensaje }}
      </div>

      <div class="row g-4">
        <!-- Columna izquierda: formulario -->
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm p-3 mb-3">
            <h6 class="mb-3">Tipo de Vehículo</h6>
            <div class="d-flex gap-2">
              <button
                v-for="tipo in tiposVehiculo"
                :key="tipo"
                type="button"
                class="btn flex-fill py-3"
                :class="tipoVehiculo === tipo ? 'btn-dark' : 'btn-outline-secondary'"
                @click="seleccionarVehiculo(tipo)"
              >
                {{ tipo }}
              </button>
            </div>
          </div>

          <div class="card border-0 shadow-sm p-3 mb-3">
            <h6 class="mb-3">Tipo de Servicio</h6>
            <div v-if="!tipoVehiculo" class="text-muted small">Primero selecciona el tipo de vehículo.</div>
            <div v-else-if="cargandoTipos" class="text-muted small">Cargando servicios disponibles...</div>
            <div v-else-if="tiposDisponibles.length === 0" class="text-muted small">
              No hay servicios con precio definido para {{ tipoVehiculo }} todavía (configúralos desde el panel de administrador).
            </div>
            <div v-else class="row g-2">
              <div class="col-md-6" v-for="tipo in tiposDisponibles" :key="tipo.tipo_servicio_id">
                <div class="form-check border rounded p-2">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    :id="'tipo-' + tipo.tipo_servicio_id"
                    :value="tipo.tipo_servicio_id"
                    v-model="tiposSeleccionados"
                  >
                  <label class="form-check-label d-flex justify-content-between" :for="'tipo-' + tipo.tipo_servicio_id">
                    <span>{{ tipo.nombre }}</span>
                    <span class="text-muted">${{ Number(tipo.precio).toLocaleString('es-CO') }}</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="card border-0 shadow-sm p-3">
            <h6 class="mb-2">Placa del Vehículo (Opcional)</h6>
            <input type="text" class="form-control" v-model="placa" placeholder="Ej: ABC-123">
          </div>
        </div>

        <!-- Columna derecha: resumen -->
        <div class="col-lg-4">
          <div class="card border-0 shadow-sm p-3">
            <h6 class="mb-3">Resumen de Venta</h6>
            <div class="d-flex justify-content-between small mb-2">
              <span>Subtotal</span>
              <span>${{ subtotal.toLocaleString('es-CO') }}</span>
            </div>
            <div class="d-flex justify-content-between small mb-3">
              <span>Impuestos (0%)</span>
              <span>$0</span>
            </div>
            <div class="border rounded p-3 text-center mb-3">
              <div class="text-muted small">Total a Pagar</div>
              <div class="fs-3 fw-bold">${{ subtotal.toLocaleString('es-CO') }}</div>
            </div>
            <button class="btn btn-dark w-100 py-2" :disabled="!puedeRegistrar || registrando" @click="registrarServicio">
              {{ registrando ? 'Registrando...' : 'Registrar Servicio' }}
            </button>
            <p class="text-muted mt-2 mb-0" style="font-size: 0.7rem;">
              El registro generará un ticket de ingreso automático.
            </p>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm p-3 mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="mb-0">Mis Servicios Recientes</h6>
          <button class="btn btn-sm btn-outline-secondary" @click="cargarMisServicios">Actualizar</button>
        </div>
        <table class="table table-sm mb-0 align-middle">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Placa</th>
              <th>Vehículo</th>
              <th>Total</th>
              <th>Duración</th>
              <th>Estado</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargandoServicios">
              <td colspan="7" class="text-center text-muted py-3">Cargando...</td>
            </tr>
            <tr v-else-if="misServicios.length === 0">
              <td colspan="7" class="text-center text-muted py-3">Aún no has registrado servicios</td>
            </tr>
            <tr v-for="s in misServicios" :key="s.id">
              <td>{{ formatearFecha(s.fecha) }}</td>
              <td>{{ s.placa }}</td>
              <td>{{ s.tipo_vehiculo }}</td>
              <td>${{ Number(s.total).toLocaleString('es-CO') }}</td>
              <td>{{ s.duracion_minutos !== null ? s.duracion_minutos + ' min' : '—' }}</td>
              <td><span class="badge bg-secondary">{{ s.estado }}</span></td>
              <td class="text-end">
                <button
                  v-if="siguienteEstado(s.estado)"
                  class="btn btn-sm btn-outline-dark"
                  @click="avanzarEstado(s)"
                >
                  Marcar como {{ siguienteEstado(s.estado) }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../services/api'

defineProps({ usuario: { type: Object, required: true } })
defineEmits(['cerrar-sesion'])

const tiposVehiculo = ['CARRO', 'MOTO', 'CAMIONETA']
const tipoVehiculo = ref('')
const tiposDisponibles = ref([])
const tiposSeleccionados = ref([])
const placa = ref('')
const cargandoTipos = ref(false)
const registrando = ref(false)
const mensaje = ref('')
const mensajeTipo = ref('success')

const seleccionarVehiculo = async (tipo) => {
  tipoVehiculo.value = tipo
  tiposSeleccionados.value = [] // las opciones cambian, no arrastramos selección del vehículo anterior
  cargandoTipos.value = true
  try {
    const res = await api.get(`/controllers/tarifas.php?tipo_vehiculo=${tipo}`)
    tiposDisponibles.value = res.data.data || []
  } catch (e) {
    mostrarMensaje('No se pudo cargar los servicios disponibles', 'error')
  } finally {
    cargandoTipos.value = false
  }
}

const subtotal = computed(() => {
  return tiposDisponibles.value
    .filter(t => tiposSeleccionados.value.includes(t.tipo_servicio_id))
    .reduce((suma, t) => suma + Number(t.precio), 0)
})

const puedeRegistrar = computed(() => tipoVehiculo.value !== '' && tiposSeleccionados.value.length > 0)

const mostrarMensaje = (texto, tipo = 'success') => {
  mensaje.value = texto
  mensajeTipo.value = tipo
  setTimeout(() => { mensaje.value = '' }, 4000)
}

const registrarServicio = async () => {
  registrando.value = true
  try {
    const res = await api.post('/controllers/servicios.php', {
      tipo_vehiculo: tipoVehiculo.value,
      tipos_servicio_ids: tiposSeleccionados.value,
      placa: placa.value
    })
    mostrarMensaje(`Servicio #${res.data.servicio.id} registrado (placa: ${res.data.servicio.placa})`)
    tipoVehiculo.value = ''
    tiposSeleccionados.value = []
    placa.value = ''
    await cargarMisServicios()
  } catch (e) {
    mostrarMensaje(e.response?.data?.message || 'Error al registrar el servicio', 'error')
  } finally {
    registrando.value = false
  }
}

const misServicios = ref([])
const cargandoServicios = ref(false)

const cargarMisServicios = async () => {
  cargandoServicios.value = true
  try {
    const res = await api.get('/controllers/servicios.php')
    misServicios.value = res.data.data || []
  } finally {
    cargandoServicios.value = false
  }
}

const secuenciaEstados = ['PENDIENTE', 'EN_PROCESO', 'LISTO', 'ENTREGADO']

const siguienteEstado = (estadoActual) => {
  const idx = secuenciaEstados.indexOf(estadoActual)
  return idx >= 0 && idx < secuenciaEstados.length - 1 ? secuenciaEstados[idx + 1] : null
}

const avanzarEstado = async (servicio) => {
  const nuevoEstado = siguienteEstado(servicio.estado)
  if (!nuevoEstado) return
  try {
    await api.put('/controllers/servicios.php', { id: servicio.id, estado: nuevoEstado })
    await cargarMisServicios()
  } catch (e) {
    mostrarMensaje(e.response?.data?.message || 'No se pudo actualizar el estado', 'error')
  }
}

const formatearFecha = (fecha) => {
  return new Date(fecha).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(() => {
  cargarMisServicios()
})
</script>