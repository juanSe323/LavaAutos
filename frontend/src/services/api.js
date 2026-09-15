import axios from 'axios'

// Instancia central de Axios para todo el proyecto.
// Cualquier módulo nuevo (tipos_servicio, empleados, etc.) debe importar esto
// en vez de usar axios directamente, así el token se adjunta solo.
const api = axios.create({
  baseURL: 'http://localhost/LavaAutos/backend'
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export default api
