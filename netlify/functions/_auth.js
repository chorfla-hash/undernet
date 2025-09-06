const jwt = require('jsonwebtoken');
const bcrypt = require('bcryptjs');
const { User } = require('./_models');


function sign(user) {
return jwt.sign({ id: user._id, role: user.role }, process.env.JWT_SECRET, { expiresIn: '7d' });
}


async function hashPassword(plain) {
const salt = await bcrypt.genSalt(10);
return bcrypt.hash(plain, salt);
}


async function verifyPassword(plain, hash) {
return bcrypt.compare(plain, hash);
}


module.exports = { sign, hashPassword, verifyPassword };
