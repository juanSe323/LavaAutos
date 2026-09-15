<template>
  <div class="d-flex justify-content-center align-items-center vh-100" style="background-color: #f5f6fa;">
    <div class="card shadow-sm p-5" style="width: 380px; border-radius: 12px;">
      <div class="text-center mb-2" style="font-size: 2.5rem;">🚗</div>
      <h4 class="text-center fw-bold mb-1">LavaAutos</h4>
      <p class="text-center text-muted small mb-4">Sistema de Gestión para Lavaderos</p>

      <form @submit.prevent="iniciarSesion">
        <div class="mb-3">
          <label class="form-label small">Correo electrónico</label>
          <div class="input-group">
            <span class="input-group-text bg-white">✉️</span>
            <input type="email" class="form-control" v-model="email" placeholder="ejemplo@lavaautos.com" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small">Contraseña</label>
          <div class="input-group">
            <span class="input-group-text bg-white">🔒</span>
            <input type="password" class="form-control" v-model="password" placeholder="********" required>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3 small">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" v-model="recordarme" id="recordarme">
            <label class="form-check-label" for="recordarme">Recordarme</label>
          </div>
          <!-- Solo visual por ahora: no hay flujo de recuperación de contraseña implementado -->
          <a href="#" class="text-decoration-none text-muted" @click.prevent>¿Olvidaste tu contraseña?</a>
        </div>

        <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

        <button type="submit" class="btn btn-dark w-100 rounded-3 py-2" :disabled="cargando">
          {{ cargando ? 'Ingresando...' : 'Iniciar Sesión' }}
        </button>
      </form>

      <hr class="my-4">
      <p class="text-center text-muted mb-0" style="font-size: 0.75rem;">LavaAutos v1.0.0 &copy; 2026</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '../services/api'

const emit = defineEmits(['login-exitoso'])

const email = ref('')
const password = ref('')
const recordarme = ref(false) // solo visual, aún no cambia el comportamiento del login
const error = ref('')
const cargando = ref(false)

const iniciarSesion = async () => {
  error.value = ''
  cargando.value = true
  try {
    const res = await api.post('/controllers/auth.php', {
      email: email.value,
      password: password.value
    })

    if (res.data.status === 'success') {
      localStorage.setItem('token', res.data.token)
      localStorage.setItem('usuario', JSON.stringify(res.data.usuario))
      emit('login-exitoso', res.data.usuario)
    } else {
      error.value = res.data.message || 'Error al iniciar sesión'
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'No se pudo conectar con el servidor'
  } finally {
    cargando.value = false
  }
}
</script>