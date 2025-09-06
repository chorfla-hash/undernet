const jwt = require('jsonwebtoken');
const { User } = require('./_models');


async function requireAuth(event) {
const header = event.headers.authorization || '';
const token = header.startsWith('Bearer ') ? header.slice(7) : null;
if (!token) return { statusCode: 401, body: 'Missing token' };
try {
const payload = jwt.verify(token, process.env.JWT_SECRET);
const user = await User.findById(payload.id).lean();
if (!user) return { statusCode: 401, body: 'User not found' };
return { user };
} catch (e) {
return { statusCode: 401, body: 'Invalid token' };
}
}


function isAdmin(user) { return user.role === 'admin'; }


module.exports = { requireAuth, isAdmin };
