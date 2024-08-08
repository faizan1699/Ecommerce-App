import ProductModel from "@/app/utils/Models/products";
import { NextResponse } from "next/server";
import connect from "@/app/utils/dbconfig/dbConfig";

export async function GET(req) {
  try {
    await connect();
    const products = await ProductModel.find();
  
    const response = NextResponse.json({
      message: "success",
      success: true,
      products: products,
    });

    return response;
  } catch (error) {
    return NextResponse.json(
      {
        message: error?.message,
      },
      { status: 500 }
    );
  }
}
