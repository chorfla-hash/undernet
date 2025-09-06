import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth'

export default function Navbar() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  return (
    <nav className="bg-zinc-950 border-b border-zinc-800 p-3 flex justify-between items-center">
      <Link to="/" className="font-bold text-xl text-white">
        DarkForum
      </Link>
      <div className="flex gap-3 items-center">
        {user ? (
          <>
            <Link to="/create" className="btn text-sm">+ Post</Link>
            <Link to="/profile" className="text-sm">{user.name}</Link>
            <button
              className="btn text-sm"
              onClick={() => {
                logout()
                navigate('/login')
              }}
            >
              Logout
            </button>
          </>
        ) : (
          <>
            <Link to="/login" className="btn text-sm">Login</Link>
            <Link to="/register" className="btn text-sm">Register</Link>
          </>
        )}
      </div>
    </nav>
  )
}
