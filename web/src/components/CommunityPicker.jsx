import { useEffect, useState } from 'react'
import api from '../api'

export default function CommunityPicker({ value, onChange }) {
  const [communities, setCommunities] = useState([])

  useEffect(() => {
    api.get('/communities-list')
      .then(r => setCommunities(r.data))
      .catch(err => console.error(err))
  }, [])

  return (
    <select
      className="input"
      value={value}
      onChange={(e) => onChange(e.target.value)}
    >
      <option value="">General</option>
      {communities.map(c => (
        <option key={c._id} value={c._id}>
          {c.name}
        </option>
      ))}
    </select>
  )
}
