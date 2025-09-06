import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import api from '../api'
import CommentList from '../components/CommentList'

export default function PostView() {
  const { id } = useParams()
  const [post, setPost] = useState(null)

  useEffect(() => {
    api.get('/posts-get', { params: { id } })
      .then(r => setPost(r.data))
      .catch(() => setPost(null))
  }, [id])

  if (!post) return <div className="card">Loading...</div>

  return (
    <div className="space-y-4">
      <div className="card space-y-2">
        <div className="text-sm text-zinc-400">
          {post.community?.name || 'general'} • {new Date(post.createdAt).toLocaleString()}
        </div>
        <div className="text-2xl font-bold">{post.title}</div>
        <div className="whitespace-pre-wrap">{post.body}</div>
        <div className="text-sm text-zinc-400">by {post.author?.name}</div>
      </div>
      <CommentList postId={id} />
    </div>
  )
}
