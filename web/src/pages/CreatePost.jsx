import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../api'
import CommunityPicker from '../components/CommunityPicker'

export default function CreatePost() {
  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')
  const [community, setCommunity] = useState('')
  const navigate = useNavigate()

  const submit = async (e) => {
    e.preventDefault()
    const { data } = await api.post('/posts-create', { title, body, community })
    navigate(`/post/${data._id}`)
  }

  return (
    <div className="card max-w-md mx-auto">
      <h1 className="text-xl font-bold mb-4">Create Post</h1>
      <form onSubmit={submit} className="space-y-3">
        <input className="input" placeholder="Title" value={title} onChange={e=>setTitle(e.target.value)} />
        <textarea className="input" placeholder="Body" value={body} onChange={e=>setBody(e.target.value)} />
        <CommunityPicker value={community} onChange={setCommunity} />
        <button className="btn w-full">Create</button>
      </form>
    </div>
  )
}
