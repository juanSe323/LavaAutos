<template>
  <div>
    <h6 class="mb-1">Tarifas por Tipo de Vehículo</h6>
    <p class="text-muted small mb-3">
      Deja el precio vacío o en 0 si ese servicio no aplica para ese tipo de vehículo
      (por ejemplo, "Aspirado" solo para Carro y Camioneta).
    </p>
    <div class="card border-0 shadow-sm">
      <table class="table mb-0 align-middle">
        <thead>
          <tr>
            <th>Servicio</th>
            <th v-for="v in tiposVehiculo" :key="v">{{ v }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td :colspan="tiposVehiculo.length + 1" class="text-center text-muted py-3">Cargando...</td>
          </tr>
          <tr v-else-if="tiposServicio.length === 0">
            <td :colspan="tiposVehiculo.length + 1" class="text-center text-muted py-3">
              Primero crea tipos de servicio en el catálogo de arriba
            </td>
          </tr>
          <tr v-for="ts in tiposServicio" :key="ts.id">
            <td>{{ ts.nombre }}</td>
            <td v-for="v in tiposVehiculo" :key="v">
              <input
                type="number" min="0" step="500"
                class="form-control form-control-sm"
                style="width: 110px;"
                v-model="matriz[ts.id][v]"
                @blur="guardarTarifa(ts.id, v)"
              >
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import api from '../../services/api'

const tiposVehiculo = ['CARRO', 'MOTO', 'CAMIONETA']
const tiposServicio = ref([])
const matriz = reactive({})
const cargando = ref(false)

const cargarDatos = async () => {
  cargando.value = true
  try {
    const [resTipos, resTarifas] = await Promise.all([
      api.get('/controllers/tipos_servicio.php'),
      api.get('/controllers/tarifas.php')
    ])

    tiposServicio.value = resTipos.data.data || []
    tiposServicio.value.forEach(ts => {
      matriz[ts.id] = { CARRO: '', MOTO: '', CAMIONETA: '' }
    })

    ;(resTarifas.data.data || []).forEach(t => {
      if (matriz[t.tipo_servicio_id]) {
        matriz[t.tipo_servicio_id][t.tipo_vehiculo] = t.precio
      }
    })
  } finally {
    cargando.value = false
  }
}

const guardarTarifa = async (tipoServicioId, tipoVehiculo) => {
  const precio = matriz[tipoServicioId][tipoVehiculo]
  try {
    await api.post('/controllers/tarifas.php', {
      tipo_servicio_id: tipoServicioId,
      tipo_vehiculo: tipoVehiculo,
      precio: precio === '' ? 0 : precio
    })
  } catch (e) {
    alert('No se pudo guardar la tarifa')
  }
}

onMounted(cargarDatos)
</script>
