const { connect } = require('./_db');
const { Comment } = require('./_models');
const { requireUser } = require('./_auth');


exports.handler = async (event) => {
await connect();
const user = await requireUser(event);
const { parentId, body } = JSON.parse(event.body || '{}');


if (!parentId || !body) return { statusCode: 400, body: 'parentId and body required' };


const parent = await Comment.findById(parentId);
if (!parent) return { statusCode: 404, body: 'Parent comment not found' };


const reply = await Comment.create({
post: parent.post,
body,
author: user._id,
parent: parent._id
});


await reply.populate('author', 'name avatarUrl');
return { statusCode: 200, body: JSON.stringify(reply) };
};
