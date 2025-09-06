const { connect } = require('./_db')
const { Comment } = require('./_models')
const { requireUser } = require('./_auth')

exports.handler = async (event) => {
  await connect()
  const user = await requireUser(event)
  const { id } = event.queryStringParameters || {}
  if (!id) return { statusCode: 400, body: 'id required' }

  const comment = await Comment.findById(id)
  if (!comment) return { statusCode: 404, body: 'Not found' }

  if (String(comment.author) !== user._id.toString() && !user.isAdmin) {
    return { statusCode: 403, body: 'Forbidden' }
  }

  await comment.deleteOne()
  return { statusCode: 200, body: JSON.stringify({ success: true }) }
}
