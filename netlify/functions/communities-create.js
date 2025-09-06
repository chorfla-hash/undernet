const { connect } = require('./_db');
const { Community } = require('./_models');
const { requireAuth } = require('./_middleware');


exports.handler = async (event) => {
if (event.httpMethod !== 'POST') return { statusCode: 405, body: 'Method not allowed' };
await connect();
const auth = await requireAuth(event);
if (auth.statusCode) return auth;
const { user } = auth;
const { name, description } = JSON.parse(event.body || '{}');
if (!name) return { statusCode: 400, body: 'name required' };
const doc = await Community.create({ name: name.trim(), description, createdBy: user._id });
return { statusCode: 200, body: JSON.stringify(doc) };
};
