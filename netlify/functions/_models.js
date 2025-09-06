const { Schema, model, models } = require('mongoose');


const UserSchema = new Schema({
email: { type: String, unique: true, required: true, lowercase: true, trim: true },
passwordHash: { type: String, required: true },
name: { type: String, default: 'anon' },
avatarUrl: { type: String },
role: { type: String, enum: ['user', 'admin'], default: 'user' },
}, { timestamps: true });


const CommunitySchema = new Schema({
name: { type: String, unique: true, required: true, trim: true },
description: String,
createdBy: { type: Schema.Types.ObjectId, ref: 'User' },
}, { timestamps: true });


const PostSchema = new Schema({
title: { type: String, required: true },
body: { type: String, required: true },
author: { type: Schema.Types.ObjectId, ref: 'User', required: true },
community: { type: Schema.Types.ObjectId, ref: 'Community' },
likes: [{ type: Schema.Types.ObjectId, ref: 'User' }],
}, { timestamps: true });


const CommentSchema = new Schema({
post: { type: Schema.Types.ObjectId, ref: 'Post', required: true },
author: { type: Schema.Types.ObjectId, ref: 'User', required: true },
body: { type: String, required: true },
}, { timestamps: true });


module.exports = {
User: models.User || model('User', UserSchema),
Community: models.Community || model('Community', CommunitySchema),
Post: models.Post || model('Post', PostSchema),
Comment: models.Comment || model('Comment', CommentSchema),
};
