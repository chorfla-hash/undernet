import { Link } from 'react-router-dom'
import api from '../api'
import { useState } from 'react'

export default function PostCard({ post, onDelete }) {
  const [likes, setLikes] = useState(post.likes?.length || 0)
  const [liked, setLiked] = useState(false)

  const toggleLike = async () => {
    try {
      const { data } = await api.post('/posts-like', { id: post._id })
      setLikes(data.likes)
      setLiked(!liked)
    } catch (err) {
      console.error(err)
    }
  }

  return (
    <div className="card space-y-2">
      <div className="text-sm text-zinc-400">
        {post.community?.name || 'general'} • {new Date(post.createdAt).toLocaleString()}
      </div>
      <Link to={`/post/${post._id}`} className="text-xl font-bold">
        {post.title}
      </Link>
      <div className="whitespace-pre-wrap">{post.body}</div>
      <div className="flex gap-3 text-sm text-zinc-400">
        <span>by {post.author?.name || 'anon'}</span>
        <button onClick={toggleLike} className="hover:underline">
          {liked ? 'Unlike' : 'Like'} ({likes})
        </button>
        {onDelete && (
          <button onClick={() => onDelete(post._id)} className="hover:underline text-red-400">
            Delete
          </button>
        )}
      </div>
    </div>
  )
}
