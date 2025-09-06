const { connect } = require('./_db');
const { User } = require('./_models');
const { verifyPassword, sign } = require('./_auth');


exports.handler = async (event) => {
if (event.httpMethod !== 'POST') return { statusCode: 405, body: 'Method not allowed' };
await connect();
const { email, password } = JSON.parse(event.body || '{}');
const user = await User.findOne({ email });
if (!user) return { statusCode: 401, body: 'Invalid credentials' };
const ok = await verifyPassword(password, user.passwordHash);
if (!ok) return { statusCode: 401, body: 'Invalid credentials' };
const token = sign(user);
return { statusCode: 200, body: JSON.stringify({ token }) };
};
