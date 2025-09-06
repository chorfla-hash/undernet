const { connect } = require('./_db');
const { User } = require('./_models');
const { hashPassword, sign } = require('./_auth');


exports.handler = async (event) => {
if (event.httpMethod !== 'POST') return { statusCode: 405, body: 'Method not allowed' };
await connect();
const { email, password, name } = JSON.parse(event.body || '{}');
if (!email || !password) return { statusCode: 400, body: 'email & password required' };
const existing = await User.findOne({ email });
if (existing) return { statusCode: 409, body: 'Email in use' };
const passwordHash = await hashPassword(password);
const user = await User.create({ email, passwordHash, name });
const token = sign(user);
return { statusCode: 200, body: JSON.stringify({ token }) };
};
