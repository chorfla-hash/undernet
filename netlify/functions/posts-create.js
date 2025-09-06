const { connect } = require('./_db');
const { Post } = require('./_models');
const { requireAuth } = require('./_middleware');


exports.handler = async (event) => {
if (event.httpMethod !== 'POST') return { statusCode: 405, body: 'Method not allowed' };
await connect();
const auth = await requireAuth(event);
if (auth.statusCode) return auth;
const { user } = auth;
const { title, body, communityId } = JSON.parse(event.body || '{}');
if (!title || !body) return { statusCode: 400, body: 'title & body required' };
const post = await Post.create({ title, body, community: communityId || null, author: user._id });
return { statusCode: 200, body: JSON.stringify(post) };
};
