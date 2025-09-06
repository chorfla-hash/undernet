const { connect } = require('./_db');
const { Post, Comment } = require('./_models');


exports.handler = async (event) => {
await connect();
const { id } = event.queryStringParameters || {};
if (!id) return { statusCode: 400, body: 'id required' };


const post = await Post.findById(id)
.populate('author', 'name avatarUrl')
.populate('community', 'name')
.lean();
if (!post) return { statusCode: 404, body: 'Not found' };


const comments = await Comment.find({ post: id })
.populate('author', 'name avatarUrl')
.sort({ createdAt: 1 })
.lean();


return { statusCode: 200, body: JSON.stringify({ post, comments }) };
};
