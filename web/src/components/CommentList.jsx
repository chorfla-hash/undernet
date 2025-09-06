import { useEffect, useState } from 'react'
import api from '../api'

export default function CommentList({ postId }) {
  const [comments, setComments] = useState([])
  const [body, setBody] = useState('')

  useEffect(() => {
    api.get('/comments-list', { params: { postId } })
      .then(r => setComments(r.data))
      .catch(err => console.error(err))
  }, [postId])

  const add = async (e) => {
    e.preventDefault()
    if (!body.trim()) return
    const { data } = await api.post('/comments-create', { postId, body })
    setComments([...comments, data])
    setBody('')
  }

  const del = async (id) => {
    await api.delete('/comments-delete', { params: { id } })
    setComments(comments.filter(c => c._id !== id))
  }

  return (
    <div className="space-y-3">
      <form onSubmit={add} className="flex gap-2">
        <input
          className="input flex-1"
          placeholder="Write a comment..."
          value={body}
          onChange={(e) => setBody(e.target.value)}
        />
        <button className="btn">Send</button>
      </form>
      {comments.map(c => (
        <div key={c._id} className="border-b border-zinc-800 pb-2">
          <div className="text-sm text-zinc-400">
            by {c.author?.name || 'anon'} • {new Date(c.createdAt).toLocaleString()}
          </div>
          <div>{c.body}</div>
          <button
            className="text-xs text-red-400 hover:underline"
            onClick={() => del(c._id)}
          >
            Delete
          </button>
        </div>
      ))}
    </div>
  )
}
