<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
      <div>
        <h5 class="mb-0">Clientes de Convenio</h5>
        <p class="text-muted small mb-0">Empresas, flotas o clientes frecuentes y sus vehículos registrados</p>
      </div>
      <button class="btn btn-primary btn-sm" @click="abrirFormularioNuevo">+ Nuevo Cliente</button>
    </div>

    <div v-if="mensaje" class="alert py-2" :class="mensajeTipo === 'error' ? 'alert-danger' : 'alert-success'">
      {{ mensaje }}
    </div>

    <div v-if="mostrarFormulario" class="card p-3 mb-3 border-0 shadow-sm">
      <h6 class="mb-3">{{ editando ? 'Editar Cliente' : 'Nuevo Cliente' }}</h6>
      <form @submit.prevent="guardar">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label small">Nombre</label>
            <input type="text" class="form-control" v-model="form.nombre" required>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Teléfono</label>
            <input type="text" class="form-control" v-model="form.telefono">
          </div>
          <div class="col-md-3">
            <label class="form-label small">Email</label>
            <input type="email" class="form-control" v-model="form.email">
          </div>
          <div class="col-md-2">
            <label class="form-label small">Convenio</label>
            <select class="form-select" v-model="form.convenio_id">
              <option :value="null">Sin convenio</option>
              <option v-for="c in convenios" :key="c.id" :value="c.id">{{ c.nombre }}</option>
            </select>
          </div>
        </div>
        <div class="mt-2">
          <button type="submit" class="btn btn-success btn-sm" :disabled="guardando">
            {{ guardando ? '...' : 'Guardar' }}
          </button>
          <button type="button" class="btn btn-link btn-sm" @click="cerrarFormulario">Cancelar</button>
        </div>
      </form>
    </div>

    <div class="card border-0 shadow-sm mb-3">
      <table class="table mb-0 align-middle">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Teléfono</th>
            <th>Convenio</th>
            <th>Vehículos</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="5" class="text-center text-muted py-3">Cargando...</td>
          </tr>
          <tr v-else-if="clientes.length === 0">
            <td colspan="5" class="text-center text-muted py-3">No hay clientes registrados</td>
          </tr>
          <tr v-for="c in clientes" :key="c.id">
            <td>{{ c.nombre }}</td>
            <td>{{ c.telefono || '—' }}</td>
            <td>
              <span v-if="c.convenio_nombre" class="badge bg-info text-dark">{{ c.convenio_nombre }}</span>
              <span v-else class="text-muted">—</span>
            </td>
            <td>
              <button class="btn btn-sm btn-outline-secondary" @click="verVehiculos(c)">
                {{ c.vehiculos_count }} {{ clienteSeleccionado === c.id ? '▲' : '▼' }}
              </button>
            </td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary me-1" @click="editar(c)">Editar</button>
              <button class="btn btn-sm btn-outline-danger" @click="eliminar(c.id)">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Vehículos del cliente seleccionado -->
    <div v-if="clienteSeleccionado" class="card border-0 shadow-sm p-3 mb-3">
      <h6 class="mb-3">Vehículos de {{ nombreClienteSeleccionado }}</h6>

      <form @submit.prevent="agregarVehiculo" class="row g-2 mb-3">
        <div class="col-md-3">
          <input type="text" class="form-control form-control-sm" v-model="formVehiculo.placa" placeholder="Placa" required>
        </div>
        <div class="col-md-3">
          <select class="form-select form-select-sm" v-model="formVehiculo.tipo" required>
            <option value="" disabled>Tipo</option>
            <option value="CARRO">Carro</option>
            <option value="MOTO">Moto</option>
            <option value="CAMIONETA">Camioneta</option>
          </select>
        </div>
        <div class="col-md-2">
          <input type="text" class="form-control form-control-sm" v-model="formVehiculo.marca" placeholder="Marca">
        </div>
        <div class="col-md-2">
          <input type="text" class="form-control form-control-sm" v-model="formVehiculo.modelo" placeholder="Modelo">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-success btn-sm w-100" :disabled="guardandoVehiculo">+ Agregar</button>
        </div>
      </form>

      <table class="table table-sm mb-0">
        <thead>
          <tr><th>Placa</th><th>Tipo</th><th>Marca</th><th>Modelo</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-if="vehiculos.length === 0">
            <td colspan="5" class="text-center text-muted py-2">Sin vehículos registrados</td>
          </tr>
          <tr v-for="v in vehiculos" :key="v.id">
            <td>{{ v.placa }}</td>
            <td>{{ v.tipo }}</td>
            <td>{{ v.marca || '—' }}</td>
            <td>{{ v.modelo || '—' }}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-danger" @click="eliminarVehiculo(v.id)">Eliminar</button>
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

const clientes = ref([])
const convenios = ref([])
const cargando = ref(false)
const guardando = ref(false)
const mostrarFormulario = ref(false)
const editando = ref(false)
const mensaje = ref('')
const mensajeTipo = ref('success')

const form = ref({ id: null, nombre: '', telefono: '', email: '', convenio_id: null })

const cargarClientes = async () => {
  cargando.value = true
  try {
    const res = await api.get('/controllers/clientes.php')
    clientes.value = res.data.data || []
  } catch (e) {
    mostrarMensaje('No se pudo cargar la lista de clientes', 'error')
  } finally {
    cargando.value = false
  }
}

const cargarConvenios = async () => {
  try {
    const res = await api.get('/controllers/convenios.php')
    convenios.value = res.data.data || []
  } catch (e) {
    // si falla, el selector de convenio simplemente queda vacío
  }
}

const mostrarMensaje = (texto, tipo = 'success') => {
  mensaje.value = texto
  mensajeTipo.value = tipo
  setTimeout(() => { mensaje.value = '' }, 3500)
}

const abrirFormularioNuevo = () => {
  form.value = { id: null, nombre: '', telefono: '', email: '', convenio_id: null }
  editando.value = false
  mostrarFormulario.value = true
}

const editar = (c) => {
  form.value = { id: c.id, nombre: c.nombre, telefono: c.telefono || '', email: c.email || '', convenio_id: c.convenio_id }
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
      await api.put('/controllers/clientes.php', form.value)
      mostrarMensaje('Cliente actualizado')
    } else {
      await api.post('/controllers/clientes.php', form.value)
      mostrarMensaje('Cliente creado')
    }
    mostrarFormulario.value = false
    await cargarClientes()
  } catch (e) {
    mostrarMensaje(e.response?.data?.message || 'Error al guardar', 'error')
  } finally {
    guardando.value = false
  }
}

const eliminar = async (id) => {
  if (!confirm('¿Eliminar este cliente?')) return
  try {
    await api.delete('/controllers/clientes.php', { data: { id } })
    mostrarMensaje('Cliente eliminado')
    await cargarClientes()
  } catch (e) {
    mostrarMensaje(e.response?.data?.message || 'Error al eliminar', 'error')
  }
}

// --- Vehículos del cliente seleccionado ---
const clienteSeleccionado = ref(null)
const nombreClienteSeleccionado = ref('')
const vehiculos = ref([])
const formVehiculo = ref({ placa: '', tipo: '', marca: '', modelo: '' })
const guardandoVehiculo = ref(false)

const verVehiculos = async (cliente) => {
  if (clienteSeleccionado.value === cliente.id) {
    clienteSeleccionado.value = null
    return
  }
  clienteSeleccionado.value = cliente.id
  nombreClienteSeleccionado.value = cliente.nombre
  formVehiculo.value = { placa: '', tipo: '', marca: '', modelo: '' }
  await cargarVehiculos(cliente.id)
}

const cargarVehiculos = async (clienteId) => {
  try {
    const res = await api.get(`/controllers/vehiculos.php?cliente_id=${clienteId}`)
    vehiculos.value = res.data.data || []
  } catch (e) {
    mostrarMensaje('No se pudo cargar los vehículos', 'error')
  }
}

const agregarVehiculo = async () => {
  guardandoVehiculo.value = true
  try {
    await api.post('/controllers/vehiculos.php', {
      ...formVehiculo.value,
      cliente_id: clienteSeleccionado.value
    })
    formVehiculo.value = { placa: '', tipo: '', marca: '', modelo: '' }
    await cargarVehiculos(clienteSeleccionado.value)
    await cargarClientes()
  } catch (e) {
    mostrarMensaje(e.response?.data?.message || 'Error al agregar el vehículo', 'error')
  } finally {
    guardandoVehiculo.value = false
  }
}

const eliminarVehiculo = async (id) => {
  if (!confirm('¿Eliminar este vehículo?')) return
  try {
    await api.delete('/controllers/vehiculos.php', { data: { id } })
    await cargarVehiculos(clienteSeleccionado.value)
    await cargarClientes()
  } catch (e) {
    mostrarMensaje(e.response?.data?.message || 'No se pudo eliminar', 'error')
  }
}

onMounted(() => {
  cargarClientes()
  cargarConvenios()
})
</script>
