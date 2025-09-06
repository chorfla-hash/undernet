const { connect } = require('./_db');
const { Post } = require('./_models');
const { requireAuth } = require('./_middleware');


exports.handler = async (event) => {
if (event.httpMethod !== 'POST
