import { NextResponse } from "next/server";

// Subcategories for each category
const subcategories = [
  {
    id: 1,
    subcategories: [
      { id: 1, name: "Mobile Phones" },
      { id: 2, name: "Laptops" },
      { id: 3, name: "Cameras" },
      { id: 4, name: "Headphones" },
    ],
  },
  {
    id: 2,
    subcategories: [
      { id: 5, name: "Men's Clothing" },
      { id: 6, name: "Women's Clothing" },
      { id: 7, name: "Accessories" },
      { id: 8, name: "Footwear" },
    ],
  },
  {
    id: 3,
    subcategories: [
      { id: 9, name: "Furniture" },
      { id: 10, name: "Kitchenware" },
      { id: 11, name: "Bedding" },
      { id: 12, name: "Home Décor" },
    ],
  },
  {
    id: 4,
    subcategories: [
      { id: 13, name: "Skincare" },
      { id: 14, name: "Haircare" },
      { id: 15, name: "Makeup" },
      { id: 16, name: "Personal Hygiene" },
    ],
  },
  {
    id: 5,
    subcategories: [
      { id: 17, name: "Fitness Equipment" },
      { id: 18, name: "Outdoor Gear" },
      { id: 19, name: "Cycling" },
      { id: 20, name: "Camping" },
    ],
  },
];

export async function GET(request) {
  const url = new URL(request.url);
  const id = parseInt(url.searchParams.get("id"), 10);
  console.log(url);
  if (isNaN(id)) {
    return NextResponse.json({ error: "Invalid category ID" }, { status: 400 });
  }

  const categorySubcategories = subcategories.find((cat) => cat.id === id);

  if (!categorySubcategories) {
    return NextResponse.json({ error: "Category not found" }, { status: 404 });
  }

  return NextResponse.json(categorySubcategories.subcategories);
}
