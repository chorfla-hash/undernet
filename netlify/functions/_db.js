const mongoose = require('mongoose');


let conn = null;


async function connect() {
if (conn) return conn;
const uri = process.env.MONGODB_URI;
if (!uri) throw new Error('MONGODB_URI missing');
conn = await mongoose.connect(uri, {
serverSelectionTimeoutMS: 5000,
});
return conn;
}


module.exports = { connect };
