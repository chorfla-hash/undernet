import { useEffect, useState } from 'react'
import api from './api'

export function useAuth() {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    async function load() {
      try {
        const { data } = await api.get('/me')
        setUser(data)
      } catch {
        setUser(null)
      }
      setLoading(false)
    }
    load()
  }, [])

  const login = async (email, password) => {
    const { data } = await api.post('/auth-login', { email, password })
    localStorage.setItem('token', data.token)
    setUser(data.user)
  }

  const register = async (email, password, name) => {
    const { data } = await api.post('/auth-register', { email, password, name })
    localStorage.setItem('token', data.token)
    setUser(data.user)
  }

  const logout = () => {
    localStorage.removeItem('token')
    setUser(null)
  }

  return { user, loading, login, register, logout }
}
