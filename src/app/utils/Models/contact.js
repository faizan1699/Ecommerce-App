import mongoose from "mongoose";

const contactSchema = new mongoose.Schema({
  name: {
    type: String,
    required: [true, "username required"],
  },
  subject: {
    type: String,
    required: [true, "subject required"],
  },
  message: {
    type: String,
    required: [true, "message required"],
  },
  email: {
    type: String,
    required: [true, "Email required"],
  },
  contacttime: {
    type: Date,
    default: Date.now,
  },
});

const Contact = mongoose.models.contacts || mongoose.model("contacts", contactSchema);
export default Contact;
