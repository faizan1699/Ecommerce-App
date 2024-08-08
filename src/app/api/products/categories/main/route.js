import { NextResponse } from "next/server";

const categories = [
  { id: 1, name: "Electronics" },
  { id: 2, name: "Fashion" },
  { id: 3, name: "Home & Furniture" },
  { id: 4, name: "Beauty & Personal Care" },
  { id: 5, name: "Sports & Outdoors" },
];

export async function GET() {
  return NextResponse.json({
    category: categories,
  });
}
