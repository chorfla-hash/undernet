const { connect } = require('./_db');
const { Comment } = require('./_models');
const { requireUser } = require('./_auth');


exports.handler = async (event) => {
await connect();
const user = await requireUser(event);
const { postId, body, parentId } = JSON.parse(event.body || '{}');


if (!postId || !body) return { statusCode: 400, body: 'postId and body required' };


const comment = await Comment.create({
post: postId,
body,
author: user._id,
parent: parentId || null
});


await comment.populate('author', 'name avatarUrl');
return { statusCode: 200, body: JSON.stringify(comment) };
};
