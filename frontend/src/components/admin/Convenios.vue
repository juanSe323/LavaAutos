<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Gestión de Convenios</h5>
      <button class="btn btn-primary btn-sm" @click="abrirFormularioNuevo">+ Nuevo Convenio</button>
    </div>

    <div v-if="mensaje" class="alert py-2" :class="mensajeTipo === 'error' ? 'alert-danger' : 'alert-success'">
      {{ mensaje }}
    </div>

    <div v-if="mostrarFormulario" class="card p-3 mb-3 border-0 shadow-sm">
      <h6 class="mb-3">{{ editando ? 'Editar Convenio' : 'Nuevo Convenio' }}</h6>
      <form @submit.prevent="guardar">
        <div class="row g-2">
          <div class="col-md-7">
            <label class="form-label small">Nombre (empresa o flota)</label>
            <input type="text" class="form-control" v-model="form.nombre" required>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Descuento (%)</label>
            <input type="number" min="0" max="100" step="0.01" class="form-control" v-model="form.descuentoPorcentaje">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-success w-100" :disabled="guardando">
              {{ guardando ? '...' : 'OK' }}
            </button>
          </div>
        </div>
        <button type="button" class="btn btn-link btn-sm mt-2 p-0" @click="cerrarFormulario">Cancelar</button>
      </form>
    </div>

    <div class="card border-0 shadow-sm">
      <table class="table mb-0 align-middle">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Descuento</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="3" class="text-center text-muted py-3">Cargando...</td>
          </tr>
          <tr v-else-if="convenios.length === 0">
            <td colspan="3" class="text-center text-muted py-3">No hay convenios registrados</td>
          </tr>
          <tr v-for="c in convenios" :key="c.id">
            <td>{{ c.nombre }}</td>
            <td>{{ Number(c.descuentoPorcentaje) }}%</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary me-1" @click="editar(c)">Editar</button>
              <button class="btn btn-sm btn-outline-danger" @click="eliminar(c.id)">Eliminar</button>
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

const convenios = ref([])
const cargando = ref(false)
const guardando = ref(false)
const mostrarFormulario = ref(false)
const editando = ref(false)
const mensaje = ref('')
const mensajeTipo = ref('success')

const form = ref({ id: null, nombre: '', descuentoPorcentaje: 0 })

const cargarConvenios = async () => {
  cargando.value = true
  try {
    const res = await api.get('/controllers/convenios.php')
    convenios.value = res.data.data || []
  } catch (e) {
    mostrarMensaje('No se pudo cargar la lista de convenios', 'error')
  } finally {
    cargando.value = false
  }
}

const mostrarMensaje = (texto, tipo = 'success') => {
  mensaje.value = texto
  mensajeTipo.value = tipo
  setTimeout(() => { mensaje.value = '' }, 3500)
}

const abrirFormularioNuevo = () => {
  form.value = { id: null, nombre: '', descuentoPorcentaje: 0 }
  editando.value = false
  mostrarFormulario.value = true
}

const editar = (c) => {
  form.value = { id: c.id, nombre: c.nombre, descuentoPorcentaje: c.descuentoPorcentaje }
  editando.value = true
  mostrarFormulario.value = true
}

const cerrarFormulario = () => {
  mostrarFormulario.value = false
}

const guardar = async () => {
  guardando.value = true
  try {
    if (editando.value) {
      await api.put('/controllers/convenios.php', form.value)
      mostrarMensaje('Convenio actualizado')
    } else {
      await api.post('/controllers/convenios.php', form.value)
      mostrarMensaje('Convenio creado')
    }
    mostrarFormulario.value = false
    await cargarConvenios()
  } catch (e) {
    mostrarMensaje(e.response?.data?.message || 'Error al guardar', 'error')
  } finally {
    guardando.value = false
  }
}

const eliminar = async (id) => {
  if (!confirm('¿Eliminar este convenio?')) return
  try {
    await api.delete('/controllers/convenios.php', { data: { id } })
    mostrarMensaje('Convenio eliminado')
    await cargarConvenios()
  } catch (e) {
    mostrarMensaje(e.response?.data?.message || 'Error al eliminar', 'error')
  }
}

onMounted(cargarConvenios)
</script>
