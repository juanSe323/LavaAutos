<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h5 class="mb-0">Catálogo de Servicios</h5>
        <p class="text-muted small mb-0">Tipos de lavado y sus precios base</p>
      </div>
      <button class="btn btn-primary btn-sm" @click="abrirFormularioNuevo">+ Nuevo Tipo de Servicio</button>
    </div>

    <div v-if="mensaje" class="alert py-2" :class="mensajeTipo === 'error' ? 'alert-danger' : 'alert-success'">
      {{ mensaje }}
    </div>

    <!-- Formulario (crear/editar) -->
    <div v-if="mostrarFormulario" class="card p-3 mb-3 border-0 shadow-sm">
      <h6 class="mb-3">{{ editando ? 'Editar Tipo de Servicio' : 'Nuevo Tipo de Servicio' }}</h6>
      <form @submit.prevent="guardar">
        <div class="row g-2">
          <div class="col-md-5">
            <label class="form-label small">Nombre</label>
            <input type="text" class="form-control" v-model="form.nombre" required>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Precio base</label>
            <input type="number" step="0.01" min="0" class="form-control" v-model="form.precioBase" required>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Tiempo estimado (min)</label>
            <input type="number" min="1" class="form-control" v-model="form.tiempo_estimado" required>
          </div>
          <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-success w-100" :disabled="guardando">
              {{ guardando ? '...' : 'OK' }}
            </button>
          </div>
        </div>
        <button type="button" class="btn btn-link btn-sm mt-2 p-0" @click="cerrarFormulario">Cancelar</button>
      </form>
    </div>

    <!-- Tabla -->
    <div class="card border-0 shadow-sm">
      <table class="table mb-0 align-middle">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Precio Base</th>
            <th>Tiempo Estimado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="4" class="text-center text-muted py-3">Cargando...</td>
          </tr>
          <tr v-else-if="tiposServicio.length === 0">
            <td colspan="4" class="text-center text-muted py-3">No hay tipos de servicio registrados</td>
          </tr>
          <tr v-for="tipo in tiposServicio" :key="tipo.id">
            <td>{{ tipo.nombre }}</td>
            <td>${{ Number(tipo.precioBase).toLocaleString('es-CO') }}</td>
            <td>{{ tipo.tiempo_estimado }} min</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary me-1" @click="editar(tipo)">Editar</button>
              <button class="btn btn-sm btn-outline-danger" @click="eliminar(tipo.id)">Eliminar</button>
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

const tiposServicio = ref([])
const cargando = ref(false)
const guardando = ref(false)
const mostrarFormulario = ref(false)
const editando = ref(false)
const mensaje = ref('')
const mensajeTipo = ref('success')

const form = ref({ id: null, nombre: '', precioBase: '', tiempo_estimado: 30 })

const cargarTiposServicio = async () => {
  cargando.value = true
  try {
    const res = await api.get('/controllers/tipos_servicio.php')
    tiposServicio.value = res.data.data || []
  } catch (e) {
    mostrarMensaje('No se pudo cargar el catálogo', 'error')
  } finally {
    cargando.value = false
  }
}

const mostrarMensaje = (texto, tipo = 'success') => {
  mensaje.value = texto
  mensajeTipo.value = tipo
  setTimeout(() => { mensaje.value = '' }, 3000)
}

const abrirFormularioNuevo = () => {
  form.value = { id: null, nombre: '', precioBase: '', tiempo_estimado: 30 }
  editando.value = false
  mostrarFormulario.value = true
}

const editar = (tipo) => {
  form.value = {
    id: tipo.id,
    nombre: tipo.nombre,
    precioBase: tipo.precioBase,
    tiempo_estimado: tipo.tiempo_estimado
  }
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
      await api.put('/controllers/tipos_servicio.php', form.value)
      mostrarMensaje('Tipo de servicio actualizado')
    } else {
      await api.post('/controllers/tipos_servicio.php', form.value)
      mostrarMensaje('Tipo de servicio creado')
    }
    mostrarFormulario.value = false
    await cargarTiposServicio()
  } catch (e) {
    mostrarMensaje(e.response?.data?.message || 'Error al guardar', 'error')
  } finally {
    guardando.value = false
  }
}

const eliminar = async (id) => {
  if (!confirm('¿Eliminar este tipo de servicio?')) return
  try {
    await api.delete('/controllers/tipos_servicio.php', { data: { id } })
    mostrarMensaje('Tipo de servicio eliminado')
    await cargarTiposServicio()
  } catch (e) {
    mostrarMensaje(e.response?.data?.message || 'Error al eliminar', 'error')
  }
}

onMounted(cargarTiposServicio)
</script>
