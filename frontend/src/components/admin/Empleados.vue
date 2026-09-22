<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Gestión de Empleados</h5>
      <button class="btn btn-primary btn-sm" @click="abrirFormularioNuevo">+ Nuevo Empleado</button>
    </div>

    <div v-if="mensaje" class="alert py-2" :class="mensajeTipo === 'error' ? 'alert-danger' : 'alert-success'">
      {{ mensaje }}
    </div>

    <div v-if="mostrarFormulario" class="card p-3 mb-3 border-0 shadow-sm">
      <h6 class="mb-3">{{ editando ? 'Editar Empleado' : 'Nuevo Empleado' }}</h6>
      <form @submit.prevent="guardar">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label small">Nombre</label>
            <input type="text" class="form-control" v-model="form.nombre" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Email</label>
            <input type="email" class="form-control" v-model="form.email" required>
          </div>
          <div class="col-md-2">
            <label class="form-label small">Teléfono</label>
            <input type="text" class="form-control" v-model="form.telefono">
          </div>
          <div class="col-md-2">
            <label class="form-label small">Modalidad</label>
            <select class="form-select" v-model="form.modalidad">
              <option value="40%">40%</option>
              <option value="50%">50%</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">{{ editando ? 'Nueva contraseña (opcional)' : 'Contraseña' }}</label>
            <input type="password" class="form-control" v-model="form.password" :required="!editando">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-success w-100" :disabled="guardando">
              {{ guardando ? '...' : 'Guardar' }}
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
            <th>Email</th>
            <th>Teléfono</th>
            <th>Modalidad</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="5" class="text-center text-muted py-3">Cargando...</td>
          </tr>
          <tr v-else-if="empleados.length === 0">
            <td colspan="5" class="text-center text-muted py-3">No hay empleados registrados</td>
          </tr>
          <tr v-for="emp in empleados" :key="emp.id">
            <td>{{ emp.nombre }}</td>
            <td>{{ emp.email }}</td>
            <td>{{ emp.telefono || '—' }}</td>
            <td>{{ emp.modalidad }}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary me-1" @click="editar(emp)">Editar</button>
              <button class="btn btn-sm btn-outline-danger" @click="eliminar(emp)">Eliminar</button>
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

const empleados = ref([])
const cargando = ref(false)
const guardando = ref(false)
const mostrarFormulario = ref(false)
const editando = ref(false)
const mensaje = ref('')
const mensajeTipo = ref('success')

const form = ref({ id: null, usuario_id: null, nombre: '', email: '', telefono: '', modalidad: '40%', password: '' })

const cargarEmpleados = async () => {
  cargando.value = true
  try {
    const res = await api.get('/controllers/empleados.php')
    empleados.value = res.data.data || []
  } catch (e) {
    mostrarMensaje('No se pudo cargar la lista de empleados', 'error')
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
  form.value = { id: null, usuario_id: null, nombre: '', email: '', telefono: '', modalidad: '40%', password: '' }
  editando.value = false
  mostrarFormulario.value = true
}

const editar = (emp) => {
  form.value = {
    id: emp.id,
    usuario_id: emp.usuario_id,
    nombre: emp.nombre,
    email: emp.email,
    telefono: emp.telefono || '',
    modalidad: emp.modalidad,
    password: ''
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
      await api.put('/controllers/empleados.php', form.value)
      mostrarMensaje('Empleado actualizado')
    } else {
      await api.post('/controllers/empleados.php', form.value)
      mostrarMensaje('Empleado creado')
    }
    mostrarFormulario.value = false
    await cargarEmpleados()
  } catch (e) {
    mostrarMensaje(e.response?.data?.message || 'Error al guardar', 'error')
  } finally {
    guardando.value = false
  }
}

const eliminar = async (emp) => {
  if (!confirm(`¿Eliminar a ${emp.nombre}? Esto también borra su acceso al sistema.`)) return
  try {
    await api.delete('/controllers/empleados.php', { data: { usuario_id: emp.usuario_id } })
    mostrarMensaje('Empleado eliminado')
    await cargarEmpleados()
  } catch (e) {
    mostrarMensaje(e.response?.data?.message || 'Error al eliminar', 'error')
  }
}

onMounted(cargarEmpleados)
</script>
