import { useEffect, useState } from 'react'
import api from '../api'
import PostCard from '../components/PostCard'

export default function Feed() {
  const [posts, setPosts] = useState([])

  useEffect(() => {
    api.get('/posts-list').then(r => setPosts(r.data))
  }, [])

  const deletePost = async (id) => {
    await api.delete('/posts-delete', { params: { id } })
    setPosts(posts.filter(p => p._id !== id))
  }

  return (
    <div className="space-y-4">
      {posts.map(p => (
        <PostCard key={p._id} post={p} onDelete={deletePost} />
      ))}
    </div>
  )
}
