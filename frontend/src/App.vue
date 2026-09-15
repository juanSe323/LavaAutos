<template>
  <div v-if="!usuario">
    <Login @login-exitoso="onLoginExitoso" />
  </div>
  <AdminLayout
    v-else-if="usuario.rol === 'ADMINISTRADOR'"
    :usuario="usuario"
    :vista-actual="vistaActual"
    @cambiar-vista="v => vistaActual = v"
    @cerrar-sesion="cerrarSesion"
  />
  <EmpleadoTerminal
    v-else
    :usuario="usuario"
    @cerrar-sesion="cerrarSesion"
  />
</template>

<script setup>
import { ref, onMounted } from 'vue'
import Login from './components/Login.vue'
import AdminLayout from './components/AdminLayout.vue'
import EmpleadoTerminal from './components/EmpleadoTerminal.vue'

const usuario = ref(null)
const vistaActual = ref('dashboard')

onMounted(() => {
  const usuarioGuardado = localStorage.getItem('usuario')
  const token = localStorage.getItem('token')
  if (usuarioGuardado && token) {
    usuario.value = JSON.parse(usuarioGuardado)
  }
})

const onLoginExitoso = (datosUsuario) => {
  usuario.value = datosUsuario
}

const cerrarSesion = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('usuario')
  usuario.value = null
  vistaActual.value = 'dashboard'
}
</script>