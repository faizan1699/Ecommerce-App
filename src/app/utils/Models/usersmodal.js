import mongoose from "mongoose";

const userSchema = new mongoose.Schema({
  name: {
    type: String,
    required: [true, "username required"],
    trim: true,
  },

  email: {
    type: String,
    required: [true, "Email required"],
    unique: true,
    lowercase: true,
    trim: true,
  },

  password: {
    type: String,
    required: [true, "password required"],
    trim: true,
  },

  isadmin: {
    type: Boolean,
    default: false,
  },

  createdAt: {
    type: Date,
    default: Date.now,
  },

  emailverify: {
    type: Boolean,
    default: false,
  },
  emailverifytoken: {
    type: String,
  },
  emailverifytokenexp: {
    type: Date,
  },
  userverificationDate: {
    type: Date,
  },
  forgerpasswordtoken: {
    type: String,
  },
  forgerpasswordtokenexp: {
    type: Date,
  },
});

const User = mongoose.models.users || mongoose.model("users", userSchema);
export default User;
