const { connect } = require('./_db');
const { Post, User, Community } = require('./_models');


exports.handler = async (event) => {
await connect();
const params = event.queryStringParameters || {};
const filter = {};
if (params.communityId) filter.community = params.communityId;
const posts = await Post.find(filter)
.sort({ createdAt: -1 })
.limit(50)
.populate('author', 'name avatarUrl')
.populate('community', 'name')
.lean();
return { statusCode: 200, body: JSON.stringify(posts) };
};
