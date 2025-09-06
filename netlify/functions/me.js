const { connect } = require('./_db');
const { User } = require('./_models');
const { requireAuth } = require('./_middleware');


exports.handler = async (event) => {
await connect();
const auth = await requireAuth(event);
if (auth.statusCode) return auth;
const { user } = auth;


if (event.httpMethod === 'GET') {
const u = await User.findById(user._id).select('-passwordHash').lean();
return { statusCode: 200, body: JSON.stringify(u) };
}
if (event.httpMethod === 'PATCH') {
const data = JSON.parse(event.body || '{}');
const updated = await User.findByIdAndUpdate(user._id, {
...(data.name && { name: data.name.slice(0, 40) }),
...(data.avatarUrl && { avatarUrl: data.avatarUrl })
}, { new: true }).select('-passwordHash');
return { statusCode: 200, body: JSON.stringify(updated) };
}
return { statusCode: 405, body: 'Method not allowed' };
};
