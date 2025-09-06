const { connect } = require('./_db')
const { Post } = require('./_models')
const { requireUser } = require('./_auth')

exports.handler = async (event, context) => {
  await connect()
  const user = await requireUser(event)
  const { id } = event.queryStringParameters || {}
  if (!id) return { statusCode: 400, body: 'id required' }

  const post = await Post.findById(id)
  if (!post) return { statusCode: 404, body: 'Not found' }

  // allow owner or admin
  if (String(post.author) !== user._id.toString() && !user.isAdmin) {
    return { statusCode: 403, body: 'Forbidden' }
  }

  await post.deleteOne()
  return { statusCode: 200, body: JSON.stringify({ success: true }) }
}
