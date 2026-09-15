<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="mb-0">Servicios Registrados</h6>
      <button class="btn btn-sm btn-outline-secondary" @click="cargarServicios">Actualizar</button>
    </div>

    <div class="card border-0 shadow-sm">
      <table class="table mb-0 align-middle">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Placa</th>
            <th>Vehículo</th>
            <th>Empleado</th>
            <th>Total</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="6" class="text-center text-muted py-3">Cargando...</td>
          </tr>
          <tr v-else-if="servicios.length === 0">
            <td colspan="6" class="text-center text-muted py-3">Aún no hay servicios registrados</td>
          </tr>
          <tr v-for="s in servicios" :key="s.id">
            <td>{{ formatearFecha(s.fecha) }}</td>
            <td>{{ s.placa }}</td>
            <td>{{ s.tipo_vehiculo }}</td>
            <td>{{ s.empleado_nombre }}</td>
            <td>${{ Number(s.total).toLocaleString('es-CO') }}</td>
            <td>
              <select
                class="form-select form-select-sm"
                :value="s.estado"
                @change="cambiarEstado(s.id, $event.target.value)"
              >
                <option value="PENDIENTE">Pendiente</option>
                <option value="EN_PROCESO">En Proceso</option>
                <option value="LISTO">Listo</option>
                <option value="ENTREGADO">Entregado</option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api'

const servicios = ref([])
const cargando = ref(false)

const cargarServicios = async () => {
  cargando.value = true
  try {
    const res = await api.get('/controllers/servicios.php')
    servicios.value = res.data.data || []
  } finally {
    cargando.value = false
  }
}

const cambiarEstado = async (id, estado) => {
  try {
    await api.put('/controllers/servicios.php', { id, estado })
    await cargarServicios()
  } catch (e) {
    alert(e.response?.data?.message || 'No se pudo actualizar el estado')
  }
}

const formatearFecha = (fecha) => {
  return new Date(fecha).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(cargarServicios)
</script>
