const { connect } = require('./_db');
const { Community } = require('./_models');


exports.handler = async () => {
await connect();
const list = await Community.find().sort({ createdAt: -1 }).limit(100).lean();
return { statusCode: 200, body: JSON.stringify(list) };
};
