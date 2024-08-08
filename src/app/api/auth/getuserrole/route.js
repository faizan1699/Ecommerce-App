import connect from "@/app/utils/dbconfig/dbConfig";
import { NextResponse } from "next/server";
import User from "@/app/utils/Models/usersmodal";

import jwt from "jsonwebtoken";

connect();

export async function POST(req) {
  try {
    const x = req.cookies.get("jwt");
    const y = x && typeof x === "object" ? x.value : x;
    const z = jwt.decode(y, { complete: true });
    const token = z.payload;

    const userdata = {
      isadmin: token.isadmin,
      name: token.name,
      email: token.email,
    };

    const response = NextResponse.json({
      message: "success",
      success: true,
      userdata,
    });

    return response;
  } catch (error) {
    return NextResponse.json({ message: error?.message }, { status: 500 });
  }
}
