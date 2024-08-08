import { NextResponse } from "next/server";
import ProductModel from "@/app/utils/Models/products";
import connect from "@/app/utils/dbconfig/dbConfig";
import { DateTime } from "luxon";

connect();

const generateProductId = () => {
  const characters =
    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
  let result = "";
  const length = 10;

  for (let i = 0; i < length; i++) {
    const randomIndex = Math.floor(Math.random() * characters.length);
    result += characters[randomIndex];
  }
  return result;
};

export async function POST(req) {
  try {
    const reqbody = await req.json();
    const {
      name,
      description,
      price,
      discountedprice,
      imgs,
      category,
      subcategry,
    } = reqbody;

    const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const currentDateTime = DateTime.now().setZone(userTimezone);

    const newProduct = new ProductModel({
      imgs,
      name,
      price,
      category,
      subcategry,
      description,
      discountedprice,
      available: true,
      id: generateProductId(),
      date: currentDateTime.toJSDate(),
    });

    await newProduct.save();

    const response = NextResponse.json(
      {
        message: "New Product Added Successfully",
        success: true,
      },
      { status: 200 }
    );

    return response;
  } catch (error) {
    return NextResponse.json({ message: error?.message }, { status: 500 });
  }
}
