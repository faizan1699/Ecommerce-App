import mongoose from "mongoose";

export const productSchema = new mongoose.Schema({
  name: {
    type: String,
    required: [true, "product name required"],
  },
  description: {
    type: String,
    required: [true, "product description required"],
  },
  price: {
    type: String,
    required: [true, "price required"],
  },
  discountedprice: {
    type: String,
  },
  id: {
    type: String,
    required: true,
    unique: true,
  },
  category: {
    type: String,
    required: [true , "category required"],
  },
  subcategry: {
    type: String,
    required: [true , "subcategory required"],
  },
  available: {
    type: Boolean,
    default: true,
  },
  date: {
    type: Date,
  },
  imgs: {
    type: [String],
    required: true,
  },
});

const ProductModel =
  mongoose.models.products || mongoose.model("products", productSchema);

export default ProductModel;
