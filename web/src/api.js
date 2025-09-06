import axios from 'axios'

// automatically point to Netlify Functions
const api = axios.create({
  baseURL: '/.netlify/functions',
})

// attach token if logged in
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

export default api
