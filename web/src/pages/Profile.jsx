import { useAuth } from '../auth'

export default function Profile() {
  const { user } = useAuth()

  if (!user) return <div className="card">Not logged in</div>

  return (
    <div className="card max-w-md mx-auto space-y-2">
      <h1 className="text-xl font-bold">Profile</h1>
      <div><strong>Name:</strong> {user.name}</div>
      <div><strong>Email:</strong> {user.email}</div>
      {user.isAdmin && <div className="text-red-400">Admin</div>}
    </div>
  )
}
